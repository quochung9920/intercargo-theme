<?php
/** Cross-section metadata and discovery for the dynamic Service Navigation section. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Add page-local Service Navigation metadata to every eligible top-level section.
 *
 * The section owns its label override, visibility, stable editor key and optional
 * fixed DOM anchor. The Service Navigation block only owns presentation order and
 * its two CTA controls.
 */
function intercargo_service_navigation_extend_section_metadata(array $metadata): array
{
    $cfg = is_array($metadata['intercargo'] ?? null) ? $metadata['intercargo'] : [];
    if (($cfg['sectionPackage'] ?? false) !== true || ($cfg['serviceNavigation'] ?? true) === false) {
        return $metadata;
    }

    if (! is_array($metadata['attributes'] ?? null)) {
        $metadata['attributes'] = [];
    }

    $metadata['attributes']['serviceNavTitle'] = [
        'type' => 'string',
        'default' => '',
    ];
    $metadata['attributes']['serviceNavHidden'] = [
        'type' => 'boolean',
        'default' => false,
    ];
    $metadata['attributes']['serviceNavKey'] = [
        'type' => 'string',
        'default' => '',
    ];

    // Custom-rendered sections already declare sectionAnchor. Composition-backed
    // sections receive the same optional attribute here so every section type can
    // opt into a fixed target ID without creating a second anchor system.
    if (! isset($metadata['attributes']['sectionAnchor'])) {
        $metadata['attributes']['sectionAnchor'] = [
            'type' => 'string',
            'default' => '',
        ];
    }

    return $metadata;
}
add_filter('block_type_metadata', 'intercargo_service_navigation_extend_section_metadata', 8);

/** @return array<string,array<string,mixed>> canonical block name => package metadata */
function intercargo_service_navigation_section_packages(): array
{
    static $packages = null;
    if (is_array($packages)) {
        return $packages;
    }

    $packages = [];
    foreach (intercargo_discover_block_packages() as $directory) {
        $metadata = intercargo_block_package_metadata($directory);
        if (! is_array($metadata)) {
            continue;
        }
        $cfg = is_array($metadata['intercargo'] ?? null) ? $metadata['intercargo'] : [];
        $name = (string) ($metadata['name'] ?? '');
        if (($cfg['sectionPackage'] ?? false) !== true || preg_match('#^intercargo/[a-z][a-z0-9-]*$#', $name) !== 1) {
            continue;
        }
        $packages[$name] = $metadata;
    }

    ksort($packages, SORT_STRING);
    return $packages;
}

function intercargo_service_navigation_package_is_eligible(string $block_name): bool
{
    $metadata = intercargo_service_navigation_section_packages()[$block_name] ?? null;
    if (! is_array($metadata)) {
        return false;
    }
    $cfg = is_array($metadata['intercargo'] ?? null) ? $metadata['intercargo'] : [];
    return ($cfg['serviceNavigation'] ?? true) !== false;
}

/** Return the default DOM anchor without mutating the runtime intercargo_section_id() counter. */
function intercargo_service_navigation_default_anchor(string $block_name): string
{
    $metadata = intercargo_service_navigation_section_packages()[$block_name] ?? null;
    if (! is_array($metadata)) {
        return '';
    }

    $cfg = is_array($metadata['intercargo'] ?? null) ? $metadata['intercargo'] : [];
    $composition_slug = (string) ($cfg['compositionSlug'] ?? '');
    if ($composition_slug !== '' && function_exists('intercargo_composition_section_config')) {
        $composition = intercargo_composition_section_config($composition_slug);
        if (is_array($composition) && ! empty($composition['id'])) {
            return (string) $composition['id'];
        }
    }

    $explicit = trim((string) ($cfg['defaultAnchor'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }

    return substr($block_name, strlen('intercargo/'));
}

function intercargo_service_navigation_clean_label(string $value): string
{
    $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    return is_string($value) ? trim($value) : '';
}

/** Find the first semantic heading stored inside a composition-backed section. */
function intercargo_service_navigation_first_heading(array $block): string
{
    if (($block['blockName'] ?? '') === 'core/heading') {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        if (isset($attrs['content']) && is_scalar($attrs['content'])) {
            $label = intercargo_service_navigation_clean_label((string) $attrs['content']);
            if ($label !== '') {
                return $label;
            }
        }

        $label = intercargo_service_navigation_clean_label((string) ($block['innerHTML'] ?? ''));
        if ($label !== '') {
            return $label;
        }
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $child) {
        if (! is_array($child)) {
            continue;
        }
        $label = intercargo_service_navigation_first_heading($child);
        if ($label !== '') {
            return $label;
        }
    }

    return '';
}

function intercargo_service_navigation_block_label(array $block): string
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $override = isset($attrs['serviceNavTitle']) && is_scalar($attrs['serviceNavTitle'])
        ? intercargo_service_navigation_clean_label((string) $attrs['serviceNavTitle'])
        : '';
    if ($override !== '') {
        return $override;
    }

    // Custom-rendered legacy/native sections keep their primary H2 in block attributes.
    foreach (['title', 'heading'] as $key) {
        if (isset($attrs[$key]) && is_scalar($attrs[$key])) {
            $label = intercargo_service_navigation_clean_label((string) $attrs[$key]);
            if ($label !== '') {
                return $label;
            }
        }
    }

    $heading = intercargo_service_navigation_first_heading($block);
    if ($heading !== '') {
        return $heading;
    }

    $name = (string) ($block['blockName'] ?? '');
    $metadata = intercargo_service_navigation_section_packages()[$name] ?? null;
    return is_array($metadata) ? intercargo_service_navigation_clean_label((string) ($metadata['title'] ?? '')) : '';
}

function intercargo_service_navigation_sanitize_id(string $value, string $fallback = ''): string
{
    $value = ltrim(trim($value), '#');
    if (function_exists('sanitize_html_class')) {
        $result = sanitize_html_class($value, $fallback);
    } else {
        $result = preg_replace('/[^A-Za-z0-9_-]/', '', $value !== '' ? $value : $fallback);
    }
    return is_string($result) ? trim($result) : '';
}

function intercargo_service_navigation_block_anchor_base(array $block): string
{
    $name = (string) ($block['blockName'] ?? '');
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $requested = isset($attrs['sectionAnchor']) && is_scalar($attrs['sectionAnchor'])
        ? (string) $attrs['sectionAnchor']
        : '';
    $fallback = intercargo_service_navigation_default_anchor($name);
    $base = intercargo_service_navigation_sanitize_id($requested, $fallback);
    return $base !== '' ? $base : $fallback;
}

function intercargo_service_navigation_block_key(array $block, string $runtime_anchor, array &$counts): string
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $requested = isset($attrs['serviceNavKey']) && is_scalar($attrs['serviceNavKey'])
        ? (string) $attrs['serviceNavKey']
        : '';
    $base = intercargo_service_navigation_sanitize_id($requested, 'nav-' . $runtime_anchor);
    if ($base === '') {
        $base = 'nav-' . $runtime_anchor;
    }
    $counts[$base] = ($counts[$base] ?? 0) + 1;
    return $counts[$base] === 1 ? $base : $base . '-' . $counts[$base];
}

/** Resolve a Core synced-pattern reference to its current stored top-level blocks. */
function intercargo_service_navigation_expand_top_level_blocks(array $blocks, array &$seen_refs = []): array
{
    $expanded = [];
    foreach ($blocks as $block) {
        if (! is_array($block) || empty($block['blockName'])) {
            continue;
        }
        if (($block['blockName'] ?? '') === 'core/block') {
            $ref = (int) (($block['attrs']['ref'] ?? 0));
            if ($ref <= 0 || isset($seen_refs[$ref]) || ! function_exists('get_post')) {
                continue;
            }
            $seen_refs[$ref] = true;
            $shared = get_post($ref);
            if ($shared instanceof WP_Post && $shared->post_type === 'wp_block' && function_exists('parse_blocks')) {
                $expanded = array_merge(
                    $expanded,
                    intercargo_service_navigation_expand_top_level_blocks(parse_blocks((string) $shared->post_content), $seen_refs)
                );
            }
            unset($seen_refs[$ref]);
            continue;
        }
        $expanded[] = $block;
    }
    return $expanded;
}

/**
 * Build the dynamic tab model from the page's actual top-level sections.
 *
 * Counts are calculated for every section from the top of the document so predicted
 * duplicate anchors (#faq-2, etc.) exactly match intercargo_section_id() at render time.
 * Only visible sections AFTER the Service Navigation instance become tabs.
 *
 * @return array<int,array{key:string,label:string,anchor:string,blockName:string}>
 */
function intercargo_service_navigation_collect_from_blocks(array $blocks): array
{
    $seen_refs = [];
    $blocks = intercargo_service_navigation_expand_top_level_blocks($blocks, $seen_refs);
    $after_navigation = false;
    $anchor_counts = [];
    $key_counts = [];
    $items = [];

    foreach ($blocks as $block) {
        if (! is_array($block)) {
            continue;
        }
        $name = (string) ($block['blockName'] ?? '');
        if ($name === 'intercargo/service-navigation') {
            if (! $after_navigation) {
                $after_navigation = true;
            }
            continue;
        }
        if (! isset(intercargo_service_navigation_section_packages()[$name])) {
            continue;
        }

        $base = intercargo_service_navigation_block_anchor_base($block);
        if ($base === '') {
            continue;
        }
        $anchor_counts[$base] = ($anchor_counts[$base] ?? 0) + 1;
        $runtime_anchor = $anchor_counts[$base] === 1 ? $base : $base . '-' . $anchor_counts[$base];

        // Structural sections still participate in ID counting, but never become tabs.
        if (! $after_navigation || ! intercargo_service_navigation_package_is_eligible($name)) {
            continue;
        }

        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        if (($attrs['serviceNavHidden'] ?? false) === true) {
            continue;
        }

        $label = intercargo_service_navigation_block_label($block);
        if ($label === '') {
            continue;
        }

        $items[] = [
            'key' => intercargo_service_navigation_block_key($block, $runtime_anchor, $key_counts),
            'label' => $label,
            'anchor' => $runtime_anchor,
            'blockName' => $name,
        ];
    }

    return $items;
}

/**
 * Apply the page-local tab presentation order while retaining dynamic discovery.
 * Missing/new sections append in their page order; stale keys are ignored.
 *
 * @param array<int,array{key:string,label:string,anchor:string,blockName:string}> $items
 * @param array<int,mixed> $order
 * @return array<int,array{key:string,label:string,anchor:string,blockName:string}>
 */
function intercargo_service_navigation_apply_order(array $items, array $order): array
{
    if ($items === [] || $order === []) {
        return $items;
    }

    $by_key = [];
    foreach ($items as $item) {
        $key = (string) ($item['key'] ?? '');
        if ($key !== '') {
            $by_key[$key] = $item;
        }
    }

    $sorted = [];
    $used = [];
    foreach ($order as $raw_key) {
        if (! is_scalar($raw_key)) {
            continue;
        }
        $key = intercargo_service_navigation_sanitize_id((string) $raw_key);
        if ($key === '' || isset($used[$key]) || ! isset($by_key[$key])) {
            continue;
        }
        $sorted[] = $by_key[$key];
        $used[$key] = true;
    }

    foreach ($items as $item) {
        $key = (string) ($item['key'] ?? '');
        if ($key === '' || ! isset($used[$key])) {
            $sorted[] = $item;
        }
    }

    return $sorted;
}

/** @return array<int,array{key:string,label:string,anchor:string,blockName:string}> */
function intercargo_service_navigation_items_for_post(int $post_id, array $tab_order = []): array
{
    if ($post_id <= 0 || ! function_exists('get_post') || ! function_exists('parse_blocks')) {
        return [];
    }
    $post = get_post($post_id);
    if (! $post instanceof WP_Post) {
        return [];
    }
    $items = intercargo_service_navigation_collect_from_blocks(parse_blocks((string) $post->post_content));
    return intercargo_service_navigation_apply_order($items, $tab_order);
}

/** Configuration used only for the unsaved live editor manager/preview. */
function intercargo_service_navigation_editor_config(): array
{
    $eligible = [];
    $anchors = [];
    foreach (intercargo_service_navigation_section_packages() as $name => $metadata) {
        if (intercargo_service_navigation_package_is_eligible($name)) {
            $eligible[] = $name;
        }
        $anchors[$name] = intercargo_service_navigation_default_anchor($name);
    }
    return ['eligible' => $eligible, 'anchors' => $anchors];
}

function intercargo_service_navigation_register_editor_config(): void
{
    $path = __DIR__ . '/editor-config.js';
    $handle = 'intercargo-service-navigation-editor-config';
    if (! is_file($path) || wp_script_is($handle, 'registered')) {
        return;
    }

    wp_register_script(
        $handle,
        get_theme_file_uri('design/sections/service-navigation/editor-config.js'),
        [],
        substr(hash_file('sha256', $path) ?: '1', 0, 16),
        true
    );
    wp_add_inline_script(
        $handle,
        'window.intercargoServiceNavigationEditorConfig = ' . wp_json_encode(intercargo_service_navigation_editor_config()) . ';',
        'before'
    );
}

function intercargo_service_navigation_enqueue_editor_config(): void
{
    intercargo_service_navigation_register_editor_config();
    wp_enqueue_script('intercargo-service-navigation-editor-config');

    $style = __DIR__ . '/editor-ui.css';
    if (is_file($style)) {
        wp_enqueue_style(
            'intercargo-service-navigation-editor-ui',
            get_theme_file_uri('design/sections/service-navigation/editor-ui.css'),
            [],
            substr(hash_file('sha256', $style) ?: '1', 0, 16)
        );
    }
}

// bootstrap.php is loaded before register_block_type(), so editor.asset.php can
// safely declare the config bridge as a dependency.
intercargo_service_navigation_register_editor_config();
add_action('enqueue_block_editor_assets', 'intercargo_service_navigation_enqueue_editor_config', 35);
