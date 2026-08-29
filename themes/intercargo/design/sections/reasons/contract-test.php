<?php
/** Content Columns About Paper preset contract. */

if (! defined('ABSPATH')) {
    require '/var/www/html/wp-load.php';
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$assert(WP_Block_Type_Registry::get_instance()->get_registered('intercargo/reasons') instanceof WP_Block_Type, 'Content Columns must be registered.');
$style = (string) file_get_contents(__DIR__ . '/style.css');
$renderer = (string) file_get_contents(__DIR__ . '/render.php');
$editor = (string) file_get_contents(__DIR__ . '/editor.js');
$assert(str_contains($style, '.content-columns--about-paper'), 'Content Columns CSS must expose the About Paper preset.');
$assert(str_contains($style, 'clamp(64px'), 'About Paper must preserve the supplied section rhythm.');
$assert(str_contains($style, '--content-columns-grid-top: clamp(32px'), 'About Paper must preserve the supplied heading-to-grid rhythm.');
$assert(str_contains($renderer, "'about-paper'"), 'The renderer must accept the About Paper preset.');
$assert(str_contains($editor, "value: 'about-paper'"), 'The editor must expose the About Paper preset.');

$template_data = json_decode((string) file_get_contents(__DIR__ . '/template.json'), true);
$template = is_array($template_data) && is_array($template_data['template'] ?? null) ? $template_data['template'] : [];
$promises = [
    ['title' => 'One written price', 'copy' => 'Every fee in the quote, including the ones that usually arrive later: destination charges, dehire, and the storage you should never have paid. Take it, or keep it as a benchmark.'],
    ['title' => 'Updates before you ask', 'copy' => 'You should never have to chase a status. If your freight moves, or fails to, you hear it from us first.'],
    ['title' => 'One team owns it', 'copy' => 'A senior operator owns your account end to end. The person who quotes your freight is the person who runs it, and nothing is handed to another company.'],
];
$item_index = 0;
$hydrate = static function (array $node) use (&$hydrate, &$item_index, $promises): array {
    $attrs = is_array($node[1] ?? null) ? $node[1] : [];
    $class = (string) ($attrs['className'] ?? '');
    if (str_contains($class, 'section-title')) {
        $attrs['content'] = 'Three promises, because these are the three things importers complain about.';
    } elseif (str_contains($class, 'content-columns-intro')) {
        $attrs['content'] = 'Every one of these exists because importers told us it was missing. They are operating rules, not marketing lines.';
    } elseif (str_contains($class, 'content-column-title')) {
        $attrs['content'] = $promises[$item_index]['title'] ?? '';
    } elseif (str_contains($class, 'content-column-copy')) {
        $attrs['content'] = $promises[$item_index]['copy'] ?? '';
        $item_index++;
    }
    $node[1] = $attrs;
    if (is_array($node[2] ?? null)) {
        $node[2] = array_map($hydrate, $node[2]);
    }
    return $node;
};
$template = array_map($hydrate, $template);

$from_template = static function (array $node) use (&$from_template): array {
    $name = (string) ($node[0] ?? '');
    $attrs = is_array($node[1] ?? null) ? $node[1] : [];
    $children = array_map($from_template, is_array($node[2] ?? null) ? $node[2] : []);
    if ($name === 'core/group') {
        $class = trim('wp-block-group ' . (string) ($attrs['className'] ?? ''));
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '<div class="' . esc_attr($class) . '"></div>', 'innerContent' => array_merge(['<div class="' . esc_attr($class) . '">'], array_fill(0, count($children), null), ['</div>'])];
    }
    if ($name === 'intercargo/content-column') {
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '', 'innerContent' => array_fill(0, count($children), null)];
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
$outer = ['blockName' => 'intercargo/reasons', 'attrs' => ['theme' => 'about-paper', 'sectionAnchor' => 'promises'], 'innerBlocks' => $inner_blocks, 'innerHTML' => '', 'innerContent' => array_fill(0, count($inner_blocks), null)];
$serialized = serialize_block($outer);
$assert(intercargo_section_composition_is_valid('reasons', $inner_blocks), 'About Paper content must pass the canonical Content Columns gate.');
$rendered = render_block($outer);
$assert(str_contains($rendered, 'content-columns--about-paper'), 'Rendered Content Columns must retain the About Paper preset.');
$assert(substr_count($rendered, 'class="reason-card content-column"') === 3, 'Three Promises must render three reusable Content Column items.');
$assert(str_contains($rendered, 'Three promises, because these are the three things importers complain about.'), 'Three Promises must render its supplied heading.');

if ($failures !== []) {
    fwrite(STDERR, "Content Columns About Paper contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Content Columns About Paper contract passed.\n";
