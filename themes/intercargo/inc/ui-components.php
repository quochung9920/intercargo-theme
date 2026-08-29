<?php
/** Auto-discovered UI primitives and motion components. */
declare(strict_types=1);

if (! defined('ABSPATH')) exit;

function intercargo_ui_component_definitions(): array
{
    $definitions = [];
    foreach (glob(get_theme_file_path('design/components/*/component.json')) ?: [] as $file) {
        $meta = json_decode((string) file_get_contents($file), true);
        if (! is_array($meta)) continue;
        $name = sanitize_key((string) ($meta['name'] ?? ''));
        if ($name === '') continue;
        $definitions[$name] = ['dir' => dirname($file), 'meta' => $meta];
    }
    ksort($definitions, SORT_STRING);
    return $definitions;
}

function intercargo_ui_asset_version(string $path): string
{
    return is_file($path) ? substr(hash_file('sha256', $path) ?: '1', 0, 16) : '1';
}

function intercargo_register_ui_components(): void
{
    foreach (intercargo_ui_component_definitions() as $name => $definition) {
        $dir = $definition['dir']; $meta = $definition['meta'];
        $style = (string) ($meta['style'] ?? '');
        if ($style !== '') {
            $path = $dir . '/' . ltrim($style, '/');
            if (is_file($path)) {
                wp_register_style(
                    'intercargo-ui-' . $name,
                    get_theme_file_uri(str_replace(intercargo_normalize_package_path(get_theme_file_path()) . '/', '', intercargo_normalize_package_path($path))),
                    ['intercargo-global'],
                    intercargo_ui_asset_version($path)
                );
            }
        }
        $script = (string) ($meta['viewScript'] ?? '');
        if ($script !== '') {
            $path = $dir . '/' . ltrim($script, '/');
            if (is_file($path)) {
                $deps = [];
                foreach ((array) ($meta['libraries'] ?? []) as $library) $deps[] = intercargo_vendor_handle((string) $library);
                wp_register_script(
                    'intercargo-ui-' . $name,
                    get_theme_file_uri(str_replace(intercargo_normalize_package_path(get_theme_file_path()) . '/', '', intercargo_normalize_package_path($path))),
                    $deps,
                    intercargo_ui_asset_version($path),
                    true
                );
            }
        }
    }
}
add_action('init', 'intercargo_register_ui_components', 31);

function intercargo_enqueue_ui_components(): void
{
    if (! intercargo_current_post_uses_intercargo_blocks()) return;
    foreach (intercargo_ui_component_definitions() as $name => $definition) {
        $meta = $definition['meta'];
        if (($meta['autoload'] ?? true) !== true) continue;
        foreach ((array) ($meta['libraries'] ?? []) as $library) intercargo_enqueue_vendor_library((string) $library);
        if (! empty($meta['style'])) wp_enqueue_style('intercargo-ui-' . $name);
        if (! empty($meta['viewScript'])) wp_enqueue_script('intercargo-ui-' . $name);
    }
}
add_action('wp_enqueue_scripts', 'intercargo_enqueue_ui_components', 22);

function intercargo_enqueue_ui_editor_styles(): void
{
    if (! is_admin()) return;
    foreach (intercargo_ui_component_definitions() as $name => $definition) {
        $meta = $definition['meta'];
        if (($meta['editorStyle'] ?? false) === true && ! empty($meta['style'])) {
            wp_enqueue_style('intercargo-ui-' . $name);
        }
    }
}
add_action('enqueue_block_assets', 'intercargo_enqueue_ui_editor_styles', 30);

function intercargo_register_ui_block_styles(): void
{
    if (! function_exists('register_block_style')) return;
    register_block_style('core/button', ['name' => 'intercargo-dark', 'label' => __('Dark', 'intercargo-vite')]);
    register_block_style('core/button', ['name' => 'intercargo-yellow', 'label' => __('Yellow', 'intercargo-vite')]);
    register_block_style('core/button', ['name' => 'intercargo-ghost', 'label' => __('Ghost', 'intercargo-vite')]);
    register_block_style('core/button', ['name' => 'intercargo-text', 'label' => __('Text / Arrow', 'intercargo-vite')]);
    register_block_style('core/group', ['name' => 'intercargo-card', 'label' => __('Interactive Card', 'intercargo-vite')]);
}
add_action('init', 'intercargo_register_ui_block_styles', 25);
