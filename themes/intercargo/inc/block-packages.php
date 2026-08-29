<?php
/** Auto-discovery for self-contained Gutenberg section/component packages. */
declare(strict_types=1);

if (! defined('ABSPATH')) exit;

function intercargo_block_package_roots(): array {
    // Final design-owned package roots. Gutenberg block identity is stored in
    // block.json, not in the filesystem path, so sections/components remain
    // stable while all visual packages live under /design.
    return [
        get_theme_file_path('design/sections'),
        get_theme_file_path('design/components'),
    ];
}
function intercargo_normalize_package_path(string $path): string {
    return function_exists('wp_normalize_path') ? wp_normalize_path($path) : str_replace('\\', '/', $path);
}
function intercargo_path_is_within(string $path, string $root): bool {
    $path = rtrim(intercargo_normalize_package_path($path), '/');
    $root = rtrim(intercargo_normalize_package_path($root), '/');
    return $path === $root || str_starts_with($path . '/', $root . '/');
}
function intercargo_is_block_package_file(string $path): bool {
    foreach (intercargo_block_package_roots() as $root) if (intercargo_path_is_within($path, $root)) return true;
    return false;
}
function intercargo_block_package_name_is_allowed(array $metadata): bool {
    $name = (string) ($metadata['name'] ?? '');
    return preg_match('#^intercargo/[a-z][a-z0-9-]*$#', $name) === 1;
}
function intercargo_discover_block_packages(): array {
    $packages = [];
    foreach (intercargo_block_package_roots() as $root) {
        if (! is_dir($root)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) {
            if (! $entry->isFile() || $entry->getFilename() !== 'block.json') continue;
            $dir = dirname($entry->getPathname());
            $meta = json_decode((string) file_get_contents($entry->getPathname()), true);
            if (is_array($meta) && intercargo_block_package_name_is_allowed($meta)) $packages[] = intercargo_normalize_package_path($dir);
        }
    }
    $packages = array_values(array_unique($packages));
    usort($packages, static function (string $a, string $b): int {
        $depth = substr_count($b, '/') <=> substr_count($a, '/');
        return $depth !== 0 ? $depth : strcmp($a, $b);
    });
    return $packages;
}
function intercargo_block_package_metadata(string $directory): ?array {
    $file = $directory . '/block.json';
    if (! is_readable($file)) return null;
    $meta = json_decode((string) file_get_contents($file), true);
    return is_array($meta) ? $meta : null;
}
function intercargo_discovered_block_package_names(): array {
    static $names = null;
    if (is_array($names)) return $names;
    $names = [];
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir);
        if ($meta && intercargo_block_package_name_is_allowed($meta)) $names[] = (string) $meta['name'];
    }
    $names = array_values(array_unique($names)); sort($names, SORT_STRING); return $names;
}

/**
 * Historical block IDs declared by canonical section packages.
 *
 * The map is metadata-driven so future LLM-created migrations remain local to the
 * section folder. Example in block.json:
 * `intercargo: { "legacyNames": ["acf/intercargo-example"] }`.
 */
function intercargo_block_package_legacy_name_map(): array {
    static $map = null;
    if (is_array($map)) return $map;

    $map = [];
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir);
        if (! $meta) continue;
        $canonical = (string) ($meta['name'] ?? '');
        if (preg_match('#^intercargo/[a-z][a-z0-9-]*$#', $canonical) !== 1) continue;

        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        $legacy_names = is_array($cfg['legacyNames'] ?? null) ? $cfg['legacyNames'] : [];
        foreach ($legacy_names as $legacy) {
            if (! is_string($legacy) || preg_match('#^acf/intercargo-[a-z][a-z0-9-]*$#', $legacy) !== 1) continue;
            if (! isset($map[$legacy])) $map[$legacy] = $canonical;
        }
    }
    ksort($map, SORT_STRING);
    return $map;
}

/** Preserve serialized attributes exactly while upgrading only historical block IDs. */
function intercargo_migrate_legacy_block_ids_in_content(string $content): string {
    foreach (intercargo_block_package_legacy_name_map() as $legacy => $canonical) {
        $content = str_replace('wp:' . $legacy, 'wp:' . $canonical, $content);
    }
    return $content;
}

function intercargo_migrate_legacy_block_ids_on_save(array $data, array $postarr): array {
    if (! isset($data['post_content']) || ! is_string($data['post_content']) || $data['post_content'] === '') return $data;
    $data['post_content'] = intercargo_migrate_legacy_block_ids_in_content($data['post_content']);
    return $data;
}
add_filter('wp_insert_post_data', 'intercargo_migrate_legacy_block_ids_on_save', 20, 2);

/**
 * Upgrade old ACF block IDs in the block editor without waiting for a manual
 * transform. Content is only persisted when WordPress performs its normal save.
 */
function intercargo_enqueue_legacy_block_id_migration(): void {
    $map = intercargo_block_package_legacy_name_map();
    if ($map === []) return;

    $path = get_theme_file_path('inc/legacy-id-migration.js');
    if (! is_file($path)) return;

    $handle = 'intercargo-legacy-id-migration';
    wp_register_script(
        $handle,
        get_theme_file_uri('inc/legacy-id-migration.js'),
        ['wp-blocks', 'wp-data'],
        substr(hash_file('sha256', $path) ?: '1', 0, 16),
        true
    );
    wp_add_inline_script($handle, 'window.intercargoLegacyBlockMap = ' . wp_json_encode($map) . ';', 'before');
    wp_enqueue_script($handle);
}
add_action('enqueue_block_editor_assets', 'intercargo_enqueue_legacy_block_id_migration', 30);
function intercargo_block_package_directory_for_composition_slug(string $slug): ?string {
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir); if (! $meta) continue;
        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        if (($cfg['compositionSlug'] ?? '') === $slug) return $dir;
    }
    return null;
}
function intercargo_package_composition_slugs(string $type): array {
    $slugs = [];
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir); if (! $meta) continue;
        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        if (($cfg['packageType'] ?? '') !== $type) continue;
        $slug = (string) ($cfg['compositionSlug'] ?? '');
        if ($slug !== '' && preg_match('/^[a-z][a-z0-9-]*$/', $slug)) $slugs[] = $slug;
    }
    $slugs = array_values(array_unique($slugs)); sort($slugs, SORT_STRING); return $slugs;
}
function intercargo_resolve_block_package_file(string $directory, mixed $reference): ?string {
    if (! is_string($reference) || ! str_starts_with($reference, 'file:')) return null;
    $relative = substr($reference, 5);
    if ($relative === '' || str_contains($relative, '\\')) return null;
    $candidate = realpath($directory . '/' . ltrim($relative, '/')); $package = realpath($directory);
    if ($candidate === false || $package === false || ! is_file($candidate)) return null;
    return intercargo_path_is_within($candidate, $package) ? intercargo_normalize_package_path($candidate) : null;
}
function intercargo_block_package_version_files(array $metadata): array {
    $metadata_file = (string) ($metadata['file'] ?? '');
    if ($metadata_file === '' || ! intercargo_is_block_package_file($metadata_file)) return [];
    $dir = dirname($metadata_file); $files = [intercargo_normalize_package_path($metadata_file)];
    foreach (['render','style','editorStyle','viewStyle','script','editorScript','viewScript','viewScriptModule'] as $key) {
        $refs = $metadata[$key] ?? []; if (! is_array($refs)) $refs = [$refs];
        foreach ($refs as $ref) {
            $file = intercargo_resolve_block_package_file($dir, $ref);
            if ($file !== null) {
                $files[] = $file;
                $asset = preg_replace('/\.(?:js|mjs|css)$/', '.asset.php', $file);
                if (is_string($asset) && is_file($asset)) $files[] = intercargo_normalize_package_path($asset);
            }
        }
    }
    foreach (['template.json','bootstrap.php'] as $local) {
        $file = $dir . '/' . $local; if (is_file($file)) $files[] = intercargo_normalize_package_path((string) realpath($file));
    }
    $assets = $dir . '/assets';
    if (is_dir($assets)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assets, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $entry) if ($entry->isFile()) $files[] = intercargo_normalize_package_path($entry->getPathname());
    }
    $files = array_values(array_unique($files)); sort($files, SORT_STRING); return $files;
}
function intercargo_version_block_package_metadata(array $metadata): array {
    $files = intercargo_block_package_version_files($metadata); if ($files === []) return $metadata;
    $ctx = hash_init('sha256'); $base = dirname((string) ($metadata['file'] ?? ''));
    foreach ($files as $file) {
        hash_update($ctx, intercargo_normalize_package_path(substr($file, strlen($base))));
        $contents = file_get_contents($file); if ($contents !== false) hash_update($ctx, $contents);
    }
    $metadata['version'] = substr(hash_final($ctx), 0, 16); return $metadata;
}

/**
 * Top-level section packages can be promoted to WordPress Core Synced Patterns.
 *
 * Local section instances remain ordinary page content. `reusable` only exposes
 * Core's explicit Create pattern / Synced workflow; it does not connect blocks
 * simply because they share the same block type.
 *
 * A package may opt out with: `intercargo: { "syncable": false }`.
 */
function intercargo_enable_section_synced_pattern_support(array $metadata): array {
    $cfg = is_array($metadata['intercargo'] ?? null) ? $metadata['intercargo'] : [];
    if (empty($cfg['sectionPackage'])) return $metadata;

    $syncable = ! array_key_exists('syncable', $cfg) || $cfg['syncable'] !== false;
    if (! is_array($metadata['supports'] ?? null)) $metadata['supports'] = [];
    $metadata['supports']['reusable'] = $syncable;
    // Section type is a design identity, not a singleton. Every top-level section
    // must be insertable more than once per page. Individual instances remain
    // local unless the editor explicitly promotes them to a shared wp_block.
    $metadata['supports']['multiple'] = true;
    return $metadata;
}
add_filter('block_type_metadata', 'intercargo_enable_section_synced_pattern_support', 10);

add_filter('block_type_metadata', 'intercargo_version_block_package_metadata', 20);


/** Historical IDs are registered as hidden aliases by inc/legacy-block-compat.php. */

function intercargo_resolve_package_editor_placeholders(mixed $value): mixed {
    if (is_string($value) && str_starts_with($value, '@theme/')) {
        $relative = substr($value, strlen('@theme/'));
        if ($relative !== '' && ! str_contains($relative, '..') && ! str_contains($relative, '\\')) {
            return get_theme_file_uri('assets/' . ltrim($relative, '/'));
        }
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) $value[$key] = intercargo_resolve_package_editor_placeholders($item);
    }
    return $value;
}
function intercargo_package_editor_definitions(): array {
    $defs = [];
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir); if (! $meta) continue;
        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        $type = (string) ($cfg['packageType'] ?? '');
        if (! in_array($type, ['section','item'], true)) continue;
        $template_file = $dir . '/template.json'; if (! is_readable($template_file)) continue;
        $definition = json_decode((string) file_get_contents($template_file), true);
        if (! is_array($definition)) continue;
        $definition = intercargo_resolve_package_editor_placeholders($definition);
        $defs[(string) $meta['name']] = ['type' => $type, 'definition' => $definition];
    }
    ksort($defs, SORT_STRING); return $defs;
}
function intercargo_register_package_editor_script(): void {
    if (wp_script_is('intercargo-package-editor', 'registered')) return;
    $path = get_theme_file_path('inc/package-editor.js'); if (! is_file($path)) return;
    $deps = ['wp-blocks','wp-block-editor','wp-components','wp-data','wp-element','wp-hooks','wp-i18n'];
    if (wp_script_is('intercargo-form-editor', 'registered')) array_unshift($deps, 'intercargo-form-editor');
    wp_register_script('intercargo-package-editor', get_theme_file_uri('inc/package-editor.js'), $deps, substr(hash_file('sha256', $path) ?: '1', 0, 16), true);
    wp_add_inline_script('intercargo-package-editor', 'window.intercargoPackageDefinitions = ' . wp_json_encode(intercargo_package_editor_definitions()) . ';', 'before');
}
function intercargo_bootstrap_block_packages(): void {
    foreach (intercargo_discover_block_packages() as $dir) {
        $file = $dir . '/bootstrap.php'; if (is_file($file)) require_once $file;
    }
}
function intercargo_register_block_packages(): void {
    if (! function_exists('register_block_type')) return;
    intercargo_bootstrap_block_packages();
    if (function_exists('intercargo_register_editor_style')) intercargo_register_editor_style();
    intercargo_register_package_editor_script();
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir);
        if (! $meta || ! intercargo_block_package_name_is_allowed($meta)) continue;
        $name = (string) ($meta['name'] ?? '');
        if ($name !== '' && class_exists('WP_Block_Type_Registry') && WP_Block_Type_Registry::get_instance()->is_registered($name)) continue;
        register_block_type($dir);
    }
}
add_action('init', 'intercargo_register_block_packages', 19);
