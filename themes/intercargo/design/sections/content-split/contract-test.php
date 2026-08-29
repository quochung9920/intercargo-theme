<?php
/**
 * Content Split package contract checks.
 */

if (! defined('ABSPATH')) {
    require '/var/www/html/wp-load.php';
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$block_type = WP_Block_Type_Registry::get_instance()->get_registered('intercargo/content-split');
$assert($block_type instanceof WP_Block_Type, 'Content Split must be registered.');
$assert(is_readable(__DIR__ . '/template.json'), 'Content Split must declare one canonical composition.');
$assert(is_readable(__DIR__ . '/style.css'), 'Content Split must own its presentation.');

$style = is_readable(__DIR__ . '/style.css') ? (string) file_get_contents(__DIR__ . '/style.css') : '';
$assert(str_contains($style, '.content-split-layout'), 'Content Split CSS must style its reusable layout.');
$assert(str_contains($style, 'grid-template-columns: minmax(0, 470fr) minmax(0, 634fr)'), 'Desktop geometry must preserve the measured 470/634 split.');
$assert(str_contains($style, '@media (min-width: 900px)'), 'Content Split must use the source-authority breakpoint.');
$assert(! str_contains($style, '<svg'), 'Content Split must not introduce SVG artwork.');

$template_data = is_readable(__DIR__ . '/template.json')
    ? json_decode((string) file_get_contents(__DIR__ . '/template.json'), true)
    : null;
$template = is_array($template_data) && is_array($template_data['template'] ?? null)
    ? $template_data['template']
    : [];
$assert(count($template) === 1, 'Content Split must have one top-level inner group.');

$from_template = static function (array $node) use (&$from_template): array {
    $name = (string) ($node[0] ?? '');
    $attrs = is_array($node[1] ?? null) ? $node[1] : [];
    $children = array_map($from_template, is_array($node[2] ?? null) ? $node[2] : []);

    if ($name === 'core/group') {
        $class = trim('wp-block-group ' . (string) ($attrs['className'] ?? ''));
        $parts = ['<div class="' . esc_attr($class) . '">'];
        foreach ($children as $_child) {
            $parts[] = null;
        }
        $parts[] = '</div>';
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $children,
            'innerHTML' => '<div class="' . esc_attr($class) . '"></div>',
            'innerContent' => $parts,
        ];
    }

    $content = wp_kses_post((string) ($attrs['content'] ?? ''));
    unset($attrs['content']);
    $class_name = trim((string) ($attrs['className'] ?? ''));
    if ($name === 'core/heading') {
        $level = max(1, min(6, (int) ($attrs['level'] ?? 2)));
        $class = trim('wp-block-heading ' . $class_name);
        $html = sprintf('<h%d class="%s">%s</h%d>', $level, esc_attr($class), $content, $level);
    } else {
        $class = trim($class_name . ' wp-block-paragraph');
        $html = '<p class="' . esc_attr($class) . '">' . $content . '</p>';
    }

    return [
        'blockName' => $name,
        'attrs' => $attrs,
        'innerBlocks' => [],
        'innerHTML' => $html,
        'innerContent' => [$html],
    ];
};

$inner_blocks = array_map($from_template, $template);
$outer = [
    'blockName' => 'intercargo/content-split',
    'attrs' => [],
    'innerBlocks' => $inner_blocks,
    'innerHTML' => '',
    'innerContent' => array_fill(0, count($inner_blocks), null),
];
$serialized = serialize_block($outer);
$parsed = array_values(array_filter(
    parse_blocks($serialized),
    static fn(array $block): bool => ($block['blockName'] ?? null) !== null
));
$assert(count($parsed) === 1, 'Content Split must serialize as one top-level block.');
$assert(intercargo_section_composition_is_valid('content-split', $inner_blocks), 'Content Split must pass the canonical composition gate.');

$rendered = render_block($outer);
$assert(str_contains($rendered, 'An Australian freight forwarder'), 'Rendered Content Split must contain the section statement.');
$assert(str_contains($rendered, 'Intercargo Connect moves freight'), 'Rendered Content Split must contain the lead paragraph.');
$assert(str_contains($rendered, 'content-split-note'), 'Rendered Content Split must retain the optional note.');
$assert(! str_contains($rendered, '<svg'), 'Rendered Content Split must not contain SVG artwork.');

if ($failures !== []) {
    fwrite(STDERR, "Content Split contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Content Split contract passed.\n";
