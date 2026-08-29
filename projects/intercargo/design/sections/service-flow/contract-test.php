<?php
/** Service Flow ownership-rail contract checks. */

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
$assert($registry->get_registered('intercargo/service-flow') instanceof WP_Block_Type, 'Service Flow must be registered.');
$assert($registry->get_registered('intercargo/service-flow-step') instanceof WP_Block_Type, 'Service Flow Step must be registered.');

$template_data = json_decode((string) file_get_contents(__DIR__ . '/template.json'), true);
$template = is_array($template_data) && is_array($template_data['template'] ?? null) ? $template_data['template'] : [];
$encoded_template = wp_json_encode($template_data);
$assert(is_string($encoded_template) && str_contains($encoded_template, 'service-flow--ownership'), 'Service Flow must expose the ownership-rail variant.');
$count_named_blocks = static function (array $nodes, string $name) use (&$count_named_blocks): int {
    $count = 0;
    foreach ($nodes as $node) {
        if (! is_array($node)) {
            continue;
        }
        if (($node[0] ?? null) === $name) {
            $count++;
        }
        if (is_array($node[2] ?? null)) {
            $count += $count_named_blocks($node[2], $name);
        }
    }
    return $count;
};
$assert($count_named_blocks($template, 'intercargo/service-flow-step') === 6, 'Ownership flow must declare six steps.');
$assert(is_string($encoded_template) && str_contains($encoded_template, 'service-flow-ownership-summary'), 'Ownership flow must include its editable summary.');

$style = (string) file_get_contents(__DIR__ . '/style.css');
$assert(str_contains($style, '.service-flow--ownership'), 'Service Flow CSS must scope ownership geometry.');
$assert(str_contains($style, '@media (min-width: 820px)'), 'Ownership flow must use the reference desktop breakpoint.');
$assert(str_contains($style, 'grid-template-columns: repeat(6, minmax(0, 1fr))'), 'Ownership flow must render six desktop columns.');
$assert(! str_contains($style, '<svg'), 'Ownership flow must not introduce SVG artwork.');

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
            'innerContent' => array_merge(['<div class="' . esc_attr($class) . '">'], array_fill(0, count($children), null), ['</div>']),
        ];
    }

    if ($name === 'intercargo/service-flow-step') {
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $children,
            'innerHTML' => '',
            'innerContent' => array_fill(0, count($children), null),
        ];
    }

    if ($name === 'core/image') {
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [],
        ];
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
$outer = [
    'blockName' => 'intercargo/service-flow',
    'attrs' => ['sectionAnchor' => 'what-we-do'],
    'innerBlocks' => $inner_blocks,
    'innerHTML' => '',
    'innerContent' => array_fill(0, count($inner_blocks), null),
];
$serialized = serialize_block($outer);
$composition_errors = intercargo_validate_composition_nodes($inner_blocks, (array) intercargo_composition_schema('service-flow'));
$assert($composition_errors === [], 'Ownership flow must pass the canonical composition gate: ' . implode('; ', $composition_errors));
$rendered = render_block($outer);
$assert(substr_count($rendered, 'class="service-flow-step"') === 6, 'Ownership flow must render six step wrappers.');
$assert(str_contains($rendered, 'What we do, end to end.'), 'Ownership flow must render its heading.');
$assert(str_contains($rendered, 'Every leg from here is ours'), 'Ownership flow must render its editable ownership label.');
$assert(! str_contains($rendered, '<svg'), 'Rendered ownership flow must not contain SVG artwork.');

if ($failures !== []) {
    fwrite(STDERR, "Service Flow ownership contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Service Flow ownership contract passed.\n";
