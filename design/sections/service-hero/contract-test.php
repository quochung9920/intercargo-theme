<?php
/**
 * Service Hero package contract checks for the reusable About layout.
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

$block_type = WP_Block_Type_Registry::get_instance()->get_registered('intercargo/service-hero');
$assert($block_type instanceof WP_Block_Type, 'Service Hero must be registered.');

$style_path = __DIR__ . '/style.css';
$style = is_readable($style_path) ? (string) file_get_contents($style_path) : '';
$assert(str_contains($style, '.about-hero-grid'), 'Service Hero CSS must expose the About layout variant.');
$assert(str_contains($style, 'grid-template-areas'), 'About Hero must use the measured two-column composition.');
$assert(str_contains($style, '@media (min-width: 1000px)'), 'About Hero must include the source-authority desktop breakpoint.');
$assert(! str_contains($style, '.about-hero-media svg'), 'About Hero must not introduce SVG artwork.');

$placeholder = get_theme_file_uri('/assets/placeholders/media-placeholder.webp');
$leaf = static function (string $name, array $attrs, string $html): array {
    return [
        'blockName' => $name,
        'attrs' => $attrs,
        'innerBlocks' => [],
        'innerHTML' => $html,
        'innerContent' => [$html],
    ];
};
$group = static function (array $attrs, array $children): array {
    $class = trim('wp-block-group ' . (string) ($attrs['className'] ?? ''));
    $inner_content = ['<div class="' . esc_attr($class) . '">'];
    foreach ($children as $_child) {
        $inner_content[] = null;
    }
    $inner_content[] = '</div>';
    return [
        'blockName' => 'core/group',
        'attrs' => $attrs,
        'innerBlocks' => $children,
        'innerHTML' => '<div class="' . esc_attr($class) . '"></div>',
        'innerContent' => $inner_content,
    ];
};

$image = $leaf(
    'core/image',
    [
        'url' => $placeholder,
        'alt' => '',
        'className' => 'about-hero-image',
        'metadata' => ['name' => 'Service hero image'],
    ],
    '<figure class="wp-block-image about-hero-image"><img src="' . esc_url($placeholder) . '" alt=""/></figure>'
);
$media = $group(
    [
        'className' => 'service-hero-media about-hero-media',
        'templateLock' => 'all',
        'metadata' => ['name' => 'Service hero media'],
    ],
    [$image]
);
$breadcrumb = $group(
    [
        'className' => 'service-hero-breadcrumb-wrap container about-hero-breadcrumb-wrap',
        'templateLock' => 'all',
        'metadata' => ['name' => 'Breadcrumb'],
    ],
    [
        $leaf(
            'core/paragraph',
            [
                'className' => 'service-hero-breadcrumb about-hero-breadcrumb',
                'metadata' => ['name' => 'Dynamic breadcrumb preview'],
            ],
            '<p class="service-hero-breadcrumb about-hero-breadcrumb">Home / About Us</p>'
        ),
    ]
);
$copy = $group(
    ['className' => 'service-hero-copy about-hero-copy', 'templateLock' => 'all'],
    [
        $leaf(
            'core/heading',
            [
                'level' => 1,
                'className' => 'service-hero-title about-hero-title',
                'metadata' => ['name' => 'Service headline'],
            ],
            '<h1 class="wp-block-heading service-hero-title about-hero-title">Forwarding, customs and warehousing. One senior team.</h1>'
        ),
        $leaf(
            'core/paragraph',
            [
                'className' => 'service-hero-lead about-hero-lead',
                'metadata' => ['name' => 'Service introduction'],
            ],
            '<p class="service-hero-lead about-hero-lead">Intercargo Connect manages every leg from our own facilities in five Australian cities, with senior people on every account.</p>'
        ),
    ]
);
$form = [
    'blockName' => 'intercargo/form',
    'attrs' => [
        'provider' => 'cf7',
        'formId' => '119',
        'variant' => 'default',
        'label' => 'About hero enquiry',
        'note' => '',
        'metadata' => ['name' => 'Service hero form'],
    ],
    'innerBlocks' => [],
    'innerHTML' => '',
    'innerContent' => [],
];
$starter = $group(
    [
        'className' => 'service-hero-starter about-hero-starter',
        'templateLock' => 'all',
        'metadata' => ['name' => 'Service enquiry starter'],
    ],
    [
        $leaf(
            'core/paragraph',
            [
                'className' => 'service-hero-starter-title about-hero-starter-title',
                'metadata' => ['name' => 'Form prompt'],
            ],
            '<p class="service-hero-starter-title about-hero-starter-title">What are you importing?</p>'
        ),
        $form,
        $leaf(
            'core/paragraph',
            [
                'className' => 'service-hero-starter-note about-hero-starter-note',
                'metadata' => ['name' => 'Response note'],
            ],
            '<p class="service-hero-starter-note about-hero-starter-note">One field to start. A senior person replies the same business day.</p>'
        ),
    ]
);
$grid = $group(
    ['className' => 'service-hero-grid about-hero-grid', 'templateLock' => 'all'],
    [$copy, $starter]
);
$card = $group(
    [
        'className' => 'service-hero-card container about-hero-card',
        'templateLock' => 'all',
        'metadata' => ['name' => 'Service hero card'],
    ],
    [$grid]
);
$outer = [
    'blockName' => 'intercargo/service-hero',
    'attrs' => [],
    'innerBlocks' => [$media, $breadcrumb, $card],
    'innerHTML' => '',
    'innerContent' => [null, null, null],
];

$serialized = serialize_block($outer);
$parsed = parse_blocks($serialized);
$meaningful = array_values(array_filter($parsed, static fn(array $block): bool => $block['blockName'] !== null));
$assert(count($meaningful) === 1, 'Serialized About Hero must parse as one top-level block.');
$assert(intercargo_section_composition_is_valid('service-hero', $outer['innerBlocks']), 'About Hero must preserve the canonical Service Hero composition.');

$rendered = render_block($outer);
$assert(str_contains($rendered, 'about-hero-grid'), 'Rendered About Hero must retain its layout variant classes.');
$assert(str_contains($rendered, 'Forwarding, customs and warehousing. One senior team.'), 'Rendered About Hero must contain the approved headline.');
$assert(str_contains($rendered, 'media-placeholder.webp'), 'Rendered About Hero must retain the reusable WebP placeholder.');
$assert(! str_contains($rendered, '<svg'), 'Rendered About Hero must not contain SVG artwork.');

if ($failures !== []) {
    fwrite(STDERR, "Service Hero About contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Service Hero About contract passed.\n";
