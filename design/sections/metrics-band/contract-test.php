<?php
/** Metrics Band package contract checks. */

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
$assert($registry->get_registered('intercargo/metrics-band') instanceof WP_Block_Type, 'Metrics Band must be registered.');
$assert($registry->get_registered('intercargo/metric') instanceof WP_Block_Type, 'Metric item must be registered.');
$assert(is_readable(__DIR__ . '/template.json'), 'Metrics Band must declare one canonical composition.');
$assert(is_readable(__DIR__ . '/style.css'), 'Metrics Band must own its presentation.');

$style = is_readable(__DIR__ . '/style.css') ? (string) file_get_contents(__DIR__ . '/style.css') : '';
$assert(str_contains($style, '.metrics-band-grid'), 'Metrics Band CSS must style the reusable collection.');
$assert(str_contains($style, '@media (min-width: 900px)'), 'Metrics Band must use the reference breakpoint.');
$assert(str_contains($style, 'justify-content: space-between'), 'Desktop metrics must distribute across the full container.');
$assert(! str_contains($style, '<svg'), 'Metrics Band must not introduce SVG artwork.');

$template_data = is_readable(__DIR__ . '/template.json')
    ? json_decode((string) file_get_contents(__DIR__ . '/template.json'), true)
    : null;
$template = is_array($template_data) && is_array($template_data['template'] ?? null)
    ? $template_data['template']
    : [];
$assert(count($template) === 1, 'Metrics Band must have one top-level inner group.');

$from_template = static function (array $node) use (&$from_template): array {
    $name = (string) ($node[0] ?? '');
    $attrs = is_array($node[1] ?? null) ? $node[1] : [];
    $children = array_map($from_template, is_array($node[2] ?? null) ? $node[2] : []);

    if ($name === 'core/group') {
        $class = trim('wp-block-group ' . (string) ($attrs['className'] ?? ''));
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $children,
            'innerHTML' => '<div class="' . esc_attr($class) . '"></div>',
            'innerContent' => array_merge(
                ['<div class="' . esc_attr($class) . '">'],
                array_fill(0, count($children), null),
                ['</div>']
            ),
        ];
    }

    if ($name === 'intercargo/metric') {
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $children,
            'innerHTML' => '',
            'innerContent' => array_fill(0, count($children), null),
        ];
    }

    $content = wp_kses_post((string) ($attrs['content'] ?? ''));
    unset($attrs['content']);
    $class = trim((string) ($attrs['className'] ?? '') . ' wp-block-paragraph');
    $html = '<p class="' . esc_attr($class) . '">' . $content . '</p>';
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
    'blockName' => 'intercargo/metrics-band',
    'attrs' => [],
    'innerBlocks' => $inner_blocks,
    'innerHTML' => '',
    'innerContent' => array_fill(0, count($inner_blocks), null),
];
$serialized = serialize_block($outer);
$parsed = array_values(array_filter(parse_blocks($serialized), static fn(array $block): bool => ($block['blockName'] ?? null) !== null));
$assert(count($parsed) === 1, 'Metrics Band must serialize as one top-level block.');
$assert(intercargo_section_composition_is_valid('metrics-band', $inner_blocks), 'Metrics Band must pass the canonical composition gate.');

$rendered = render_block($outer);
$assert(substr_count($rendered, 'class="metric-item"') === 4, 'Metrics Band must render four metric items.');
$assert(str_contains($rendered, '>Same day<'), 'Metrics Band must render text values without numeric assumptions.');
$assert(str_contains($rendered, 'a senior person replies'), 'Metrics Band must render metric descriptions.');
$assert(! str_contains($rendered, '<svg'), 'Rendered Metrics Band must not contain SVG artwork.');

if ($failures !== []) {
    fwrite(STDERR, "Metrics Band contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Metrics Band contract passed.\n";
