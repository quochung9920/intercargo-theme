<?php
/** Vite manifest resolver and fail-closed asset registration. */
declare(strict_types=1);

function intercargo_manifest(): array
{
    static $manifest;
    if (is_array($manifest)) return $manifest;
    $path = get_theme_file_path('dist/.vite/manifest.json');
    if (! is_readable($path)) return $manifest = [];
    $decoded = json_decode((string) file_get_contents($path), true);
    return $manifest = is_array($decoded) ? $decoded : [];
}

function intercargo_manifest_file(string $entry, string $extension): ?string
{
    $manifest = intercargo_manifest();
    $file = $manifest[$entry]['file'] ?? null;
    if (! is_string($file) || ! in_array($extension, ['css', 'js'], true)) return null;
    if (str_contains($file, '..') || str_starts_with($file, '/') || str_contains($file, '\\')) return null;
    if (pathinfo($file, PATHINFO_EXTENSION) !== $extension) return null;
    if (! preg_match('/-[A-Za-z0-9_-]{8,}\.' . preg_quote($extension, '/') . '$/', basename($file))) return null;
    if (! is_file(get_theme_file_path('dist/' . $file))) return null;
    return $file;
}

function intercargo_register_manifest_style(string $entry, string $handle, array $dependencies = []): ?string
{
    if (wp_style_is($handle, 'registered')) return $handle;
    $file = intercargo_manifest_file($entry, 'css');
    if ($file === null) return null;
    return wp_register_style($handle, get_theme_file_uri('dist/' . $file), $dependencies, null) ? $handle : null;
}

function intercargo_register_global_style(): ?string
{
    return intercargo_register_manifest_style('src/css/global.css', 'intercargo-global');
}

function intercargo_register_editor_style(): ?string
{
    $global = intercargo_register_global_style();
    if ($global === null) return null;
    return intercargo_register_manifest_style('src/css/editor.css', 'intercargo-editor', [$global]);
}

function intercargo_enqueue_theme_assets(): void
{
    $global = intercargo_register_global_style();
    if ($global !== null) wp_enqueue_style($global);
    $js = intercargo_manifest_file('src/js/site.js', 'js');
    if ($js !== null) wp_enqueue_script('intercargo-site', get_theme_file_uri('dist/' . $js), [], null, true);
}

/**
 * Deliver theme styles to the block editor canvas.
 *
 * Fires on `enqueue_block_assets`, which runs inside the editor iframe and on the
 * frontend. The frontend path is already served by `wp_enqueue_scripts`, so this
 * only acts in the admin context. Per-block CSS arrives through the `style` and
 * `editorStyle` handles declared in each `block.json`.
 */
function intercargo_enqueue_block_assets(): void
{
    if (! is_admin()) return;
    $global = intercargo_register_global_style();
    if ($global !== null) wp_enqueue_style($global);
}

function intercargo_register_editor_script(): ?string
{
    if (wp_script_is('intercargo-editor-format', 'registered')) return 'intercargo-editor-format';
    $file = intercargo_manifest_file('src/js/editor.js', 'js');
    if ($file === null) return null;
    $dependencies = ['wp-rich-text', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-data', 'wp-hooks', 'wp-components', 'wp-server-side-render'];
    if (wp_script_is('intercargo-package-editor', 'registered')) {
        array_unshift($dependencies, 'intercargo-package-editor');
    }
    $registered = wp_register_script(
        'intercargo-editor-format',
        get_theme_file_uri('dist/' . $file),
        $dependencies,
        null,
        true
    );
    return $registered ? 'intercargo-editor-format' : null;
}

/**
 * Editor JavaScript only. Canvas CSS is delivered through block.json handles on
 * the `enqueue_block_assets` hook, which runs inside the iframe.
 */
function intercargo_enqueue_editor_assets(): void
{
    $handle = intercargo_register_editor_script();
    if ($handle !== null) wp_enqueue_script($handle);
}


function intercargo_theme_file_uri_from_path(string $path): string
{
    $theme_root = realpath(get_theme_file_path());
    $real = realpath($path);
    if ($theme_root === false || $real === false || ! is_file($real)) return '';
    $theme_root = rtrim(wp_normalize_path($theme_root), '/');
    $real = wp_normalize_path($real);
    if (! str_starts_with($real . '/', $theme_root . '/')) return '';
    $relative = ltrim(substr($real, strlen($theme_root)), '/');
    return $relative !== '' ? get_theme_file_uri($relative) : '';
}

function intercargo_asset_url(string $file): string
{
    $file = ltrim($file, '/');
    if ($file === '' || str_contains($file, '..') || str_contains($file, '\\')) return '';
    $path = get_theme_file_path('assets/' . $file);
    return is_file($path) ? get_theme_file_uri('assets/' . $file) : '';
}
