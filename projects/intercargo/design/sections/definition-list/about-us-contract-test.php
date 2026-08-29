<?php
/** Contract for all remaining About Us sections. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    require '/var/www/html/wp-load.php';
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$theme_uri = rtrim(get_theme_file_uri(), '/');
$from_template = static function (array $node) use (&$from_template, $theme_uri): array {
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

    if (str_starts_with($name, 'intercargo/')) {
        return [
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => $children,
            'innerHTML' => '',
            'innerContent' => array_fill(0, count($children), null),
        ];
    }

    if ($name === 'core/image') {
        $url = (string) ($attrs['url'] ?? '');
        if (str_starts_with($url, '@theme/')) {
            $url = $theme_uri . '/assets/' . ltrim(substr($url, 7), '/');
        }
        $attrs['url'] = $url;
        $class = trim('wp-block-image ' . (string) ($attrs['className'] ?? ''));
        $html = '<figure class="' . esc_attr($class) . '"><img src="' . esc_url($url) . '" alt="' . esc_attr((string) ($attrs['alt'] ?? '')) . '"></figure>';
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

$fixtures = [
    'service-options' => dirname(__DIR__) . '/service-options/about-run.template.json',
    'definition-list' => __DIR__ . '/template.json',
    'service-cta' => dirname(__DIR__) . '/service-cta/about-statement.template.json',
    'media-split' => dirname(__DIR__) . '/media-split/about-floor.template.json',
    'content-split' => dirname(__DIR__) . '/content-split/about-deal.template.json',
];
$expected = [
    'service-options' => 'What we actually run.',
    'definition-list' => 'The things you can check.',
    'service-cta' => 'Operators and brokers. Not a sales office.',
    'media-split' => 'The floor, not the boardroom.',
    'content-split' => 'Who you will deal with.',
];

foreach ($fixtures as $slug => $path) {
    $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    $assert(is_array($data), $slug . ' About fixture must be valid JSON.');
    if (! is_array($data)) {
        continue;
    }
    $nodes = array_map($from_template, (array) ($data['template'] ?? []));
    $errors = intercargo_validate_composition_nodes($nodes, (array) intercargo_composition_schema($slug));
    $assert($errors === [], $slug . ' fixture must pass composition: ' . implode('; ', $errors));
    $outer = [
        'blockName' => 'intercargo/' . $slug,
        'attrs' => ['sectionAnchor' => 'about-' . $slug],
        'innerBlocks' => $nodes,
        'innerHTML' => '',
        'innerContent' => array_fill(0, count($nodes), null),
    ];
    $serialized = serialize_block($outer);
    $rendered = render_block($outer);
    $assert($serialized !== '', $slug . ' fixture must serialize.');
    $assert(str_contains($rendered, $expected[$slug]), $slug . ' fixture must render its visible heading.');
    if ($slug === 'definition-list') {
        $assert(substr_count($rendered, 'class="definition-row"') === 5, 'Definition List must render all five row wrappers.');
    }
    if ($slug === 'service-cta') {
        $assert(str_contains($rendered, 'action="#about-enquiry"'), 'Quick enquiry must target the About Us enquiry section.');
    }
    $assert(! str_contains($rendered, '<svg'), $slug . ' fixture must not render SVG.');
}

$quick_form = intercargo_render_form_adapter(['variant' => 'quick-enquiry', 'label' => 'Quick import enquiry']);
$assert(str_contains($quick_form, 'intercargo-quick-enquiry'), 'Quick enquiry variant must render a real form.');
$assert(str_contains($quick_form, 'name="q"'), 'Quick enquiry must submit the importing summary.');
$assert(str_contains($quick_form, 'action="#about-enquiry"'), 'Quick enquiry must target the About Us enquiry section.');

$run_icon = dirname(__DIR__) . '/service-options/assets/chevron-right.png';
$assert(is_file($run_icon), 'About Run must provide a raster chevron asset.');
if (is_file($run_icon)) {
    $size = getimagesize($run_icon);
    $assert(($size[0] ?? 0) === 20 && ($size[1] ?? 0) === 20, 'About Run chevron must be exactly 20 by 20 pixels.');
    $assert(($size['mime'] ?? '') === 'image/png', 'About Run chevron must be PNG.');
}
$run_css = (string) file_get_contents(dirname(__DIR__) . '/service-options/style.css');
$assert(str_contains($run_css, 'assets/chevron-right.png'), 'About Run CSS must use the raster chevron asset.');

$enquiry = [
    'blockName' => 'intercargo/enquiry',
    'attrs' => [
        'title' => 'Talk to the team.',
        'lead' => 'A senior person replies the same business day, with a real answer, not a ticket number.',
        'steps' => [
            ['title' => 'We reply the same business day', 'copy' => 'A senior person, across your enquiry.'],
            ['title' => 'You get one written price', 'copy' => 'Every fee included. Take it or keep it as a benchmark.'],
        ],
        'formLabel' => 'Talk to the team',
        'formNote' => 'A person replies. No quote-bot, no ticket queue.',
        'provider' => 'cf7',
        'formId' => '117',
        'sectionAnchor' => 'about-enquiry',
    ],
    'innerBlocks' => [],
    'innerHTML' => '',
    'innerContent' => [],
];
$enquiry_html = render_block($enquiry);
$assert(str_contains($enquiry_html, 'Talk to the team.'), 'Final enquiry must render its heading.');
$assert(str_contains($enquiry_html, 'wpcf7'), 'Final enquiry must render the selected Contact Form 7 form.');
$assert(! str_contains(strtolower($enquiry_html), '<svg'), 'Final enquiry must not render SVG.');

if ($failures !== []) {
    fwrite(STDERR, "Remaining About Us contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Remaining About Us contract passed.\n";
