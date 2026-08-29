<?php
/** Media Mosaic package contract checks. */

if (! defined('ABSPATH')) {
    require '/var/www/html/wp-load.php';
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$registry = WP_Block_Type_Registry::get_instance();
$assert($registry->get_registered('intercargo/media-mosaic') instanceof WP_Block_Type, 'Media Mosaic must be registered.');
$assert($registry->get_registered('intercargo/media-tile') instanceof WP_Block_Type, 'Media Tile must be registered.');
$assert(is_readable(__DIR__ . '/template.json'), 'Media Mosaic must declare a canonical composition.');
$style = is_readable(__DIR__ . '/style.css') ? (string) file_get_contents(__DIR__ . '/style.css') : '';
$assert(str_contains($style, '.media-mosaic-grid'), 'Media Mosaic CSS must own its reusable collection geometry.');
$assert(str_contains($style, '@media (min-width: 768px)'), 'Media Mosaic must enter the Gutenberg Tablet range at 768px.');
$assert(str_contains($style, '@media (min-width: 1024px)'), 'Media Mosaic must enter the Gutenberg Desktop range at 1024px.');
$assert(! str_contains($style, '@media (min-width: 576px)'), 'Media Mosaic must not expose a fourth device range outside Gutenberg preview modes.');
$assert(str_contains($style, '.media-mosaic-section .media-mosaic-grid.is-layout-grid'), 'Media Mosaic must override the Core Group auto-fill grid with sufficient specificity.');
$assert(str_contains($style, 'repeat(12, minmax(0, 1fr))'), 'Every device layout must retain a twelve-column grid.');
$assert(str_contains($style, 'grid-column: auto / span'), 'Media Tile placement must span from an auto start without creating implicit grid tracks.');
$assert(str_contains($style, '--media-mosaic-span-mobile'), 'Media Tile must expose an independent mobile span.');
$assert(str_contains($style, '--media-mosaic-span-tablet'), 'Media Tile must expose an independent Gutenberg Tablet span.');
$assert(str_contains($style, '--media-mosaic-span-desktop'), 'Media Tile must expose an independent Gutenberg Desktop span.');
$tile_metadata = json_decode((string) file_get_contents(__DIR__ . '/blocks/media-tile/block.json'), true);
foreach (['spanMobile', 'spanTablet', 'spanDesktop'] as $attribute_name) {
    $assert(($tile_metadata['attributes'][$attribute_name]['type'] ?? '') === 'integer', "Media Tile must declare {$attribute_name} as an integer.");
}
$assert(is_readable(__DIR__ . '/blocks/media-tile/editor.js'), 'Media Tile must ship per-device width controls.');
$tile_editor = is_readable(__DIR__ . '/blocks/media-tile/editor.js') ? (string) file_get_contents(__DIR__ . '/blocks/media-tile/editor.js') : '';
$assert(str_contains($tile_editor, 'getDeviceType'), 'Media Tile editor must read the active Gutenberg preview device.');
$assert(str_contains($tile_editor, '__experimentalGetPreviewDeviceType'), 'Media Tile editor must support the legacy Gutenberg preview selector.');
foreach (['Mobile', 'Tablet', 'Desktop'] as $control_label) {
    $assert(str_contains($tile_editor, "'{$control_label}'"), "Media Tile editor must support the {$control_label} preview mode.");
}
$assert(! str_contains($style, 'linear-gradient'), 'Media Mosaic must not draw substitute artwork with CSS.');
$assert(! str_contains($style, '<svg'), 'Media Mosaic must not introduce SVG artwork.');

$template_data = json_decode((string) file_get_contents(__DIR__ . '/template.json'), true);
$template_data = intercargo_resolve_package_editor_placeholders($template_data);
$template = is_array($template_data) && is_array($template_data['template'] ?? null) ? $template_data['template'] : [];

$from_template = static function (array $node) use (&$from_template): array {
    $name = (string) ($node[0] ?? '');
    $attrs = is_array($node[1] ?? null) ? $node[1] : [];
    $children = array_map($from_template, is_array($node[2] ?? null) ? $node[2] : []);
    if ($name === 'core/group') {
        $class = trim('wp-block-group ' . (string) ($attrs['className'] ?? ''));
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '<div class="' . esc_attr($class) . '"></div>', 'innerContent' => array_merge(['<div class="' . esc_attr($class) . '">'], array_fill(0, count($children), null), ['</div>'])];
    }
    if ($name === 'intercargo/media-tile') {
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '', 'innerContent' => array_fill(0, count($children), null)];
    }
    if ($name === 'core/image') {
        $url = esc_url((string) ($attrs['url'] ?? ''));
        $alt = esc_attr((string) ($attrs['alt'] ?? ''));
        $class = esc_attr(trim('wp-block-image ' . (string) ($attrs['className'] ?? '')));
        $html = '<figure class="' . $class . '"><img src="' . $url . '" alt="' . $alt . '"></figure>';
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => [$html]];
    }
    $content = wp_kses_post((string) ($attrs['content'] ?? ''));
    unset($attrs['content']);
    $class_name = trim((string) ($attrs['className'] ?? ''));
    if ($name === 'core/heading') {
        $level = max(1, min(6, (int) ($attrs['level'] ?? 2)));
        $html = sprintf('<h%d class="%s">%s</h%d>', $level, esc_attr(trim('wp-block-heading ' . $class_name)), $content, $level);
    } else {
        $html = '<p class="' . esc_attr(trim($class_name . ' wp-block-paragraph')) . '">' . $content . '</p>';
    }
    return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => [$html]];
};

$inner_blocks = array_map($from_template, $template);
$outer = ['blockName' => 'intercargo/media-mosaic', 'attrs' => ['sectionAnchor' => 'operation'], 'innerBlocks' => $inner_blocks, 'innerHTML' => '', 'innerContent' => array_fill(0, count($inner_blocks), null)];
$serialized = serialize_block($outer);
$assert(intercargo_section_composition_is_valid('media-mosaic', $inner_blocks), 'Media Mosaic must pass its canonical composition gate.');
$rendered = render_block($outer);
$assert(substr_count($rendered, '<div class="media-mosaic-tile') === 4, 'The Operation must render four Media Tile items.');
$assert(substr_count($rendered, '--media-mosaic-span-mobile:12') === 4, 'Default mobile tiles must span twelve columns.');
$assert(substr_count($rendered, '--media-mosaic-span-tablet:4') === 4, 'Default Gutenberg Tablet tiles must span four columns.');
$assert(substr_count($rendered, '--media-mosaic-span-desktop:3') === 4, 'Default Gutenberg Desktop tiles must span three columns.');
$assert(substr_count($rendered, 'media-placeholder.webp') === 2, 'Missing Air and Road photography must retain two WebP placeholders.');
$assert(str_contains($rendered, '6ea7bb3c143f74e411d5b49ab617c6acb1fdbfea.png'), 'Sea must reuse the available PNG asset.');
$assert(str_contains($rendered, '01fe034c8270ad81b014007611c673e085a9effd.png'), 'Warehouse must reuse the available PNG asset.');
$assert(! str_contains($rendered, '<svg'), 'Rendered Media Mosaic must not contain SVG artwork.');

$find_first_named = static function (array $nodes, string $name) use (&$find_first_named): ?array {
    foreach ($nodes as $node) {
        if (($node['blockName'] ?? null) === $name) {
            return $node;
        }
        $nested = $find_first_named((array) ($node['innerBlocks'] ?? []), $name);
        if ($nested !== null) {
            return $nested;
        }
    }
    return null;
};
$sample_tile = $find_first_named($inner_blocks, 'intercargo/media-tile');
$assert(is_array($sample_tile), 'The fixture must expose a Media Tile for responsive span testing.');
if (is_array($sample_tile)) {
    $sample_tile['attrs'] = array_merge($sample_tile['attrs'], ['spanMobile' => 10, 'spanTablet' => 5, 'spanDesktop' => 2]);
    $sample_render = render_block($sample_tile);
    foreach (['mobile:10', 'tablet:5', 'desktop:2'] as $responsive_value) {
        $assert(str_contains($sample_render, '--media-mosaic-span-' . $responsive_value), 'Each device span must render independently.');
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Media Mosaic contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Media Mosaic contract passed.\n";
