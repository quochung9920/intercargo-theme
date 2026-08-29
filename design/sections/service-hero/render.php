<?php
/** Service Hero — frontend render with a real WordPress hierarchy breadcrumb. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$service_hero_content = (string) ($content ?? '');
$post_id = is_singular() ? (int) get_queried_object_id() : 0;

if ($post_id > 0) {
    $trail = [];
    $front_page_id = (int) get_option('page_on_front');

    // Root is always the public site home. If the current page is the front page,
    // the single current crumb is enough and avoids a duplicated Home / Home trail.
    if ($front_page_id > 0 && $post_id === $front_page_id) {
        $trail[] = [
            'label' => (string) get_the_title($post_id),
            'url' => '',
            'current' => true,
        ];
    } else {
        $trail[] = [
            'label' => __('Home', 'intercargo-vite'),
            'url' => home_url('/'),
            'current' => false,
        ];

        // WordPress returns closest parent first. Reverse it so the trail reads
        // from the root ancestor down to the current page.
        $ancestors = array_reverse(array_map('intval', (array) get_post_ancestors($post_id)));
        foreach ($ancestors as $ancestor_id) {
            if ($ancestor_id <= 0 || ($front_page_id > 0 && $ancestor_id === $front_page_id)) {
                continue;
            }

            $label = trim((string) get_the_title($ancestor_id));
            $url = (string) get_permalink($ancestor_id);
            if ($label === '' || $url === '') {
                continue;
            }

            $trail[] = [
                'label' => $label,
                'url' => $url,
                'current' => false,
            ];
        }

        $current_label = trim((string) get_the_title($post_id));
        if ($current_label === '') {
            $current_label = __('Current page', 'intercargo-vite');
        }
        $trail[] = [
            'label' => $current_label,
            'url' => '',
            'current' => true,
        ];
    }

    /**
     * Allow project-specific hierarchy additions without making the Service Hero
     * own a second breadcrumb data model.
     */
    $trail = apply_filters('intercargo_service_hero_breadcrumb_items', $trail, $post_id);

    if (is_array($trail) && $trail !== []) {
        $items_html = '';
        foreach ($trail as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $is_current = ! empty($item['current']);
            $url = trim((string) ($item['url'] ?? ''));

            if (! $is_current && $url !== '') {
                $items_html .= sprintf(
                    '<li class="service-hero-breadcrumb-item"><a href="%1$s">%2$s</a></li>',
                    esc_url($url),
                    esc_html($label)
                );
            } else {
                $items_html .= sprintf(
                    '<li class="service-hero-breadcrumb-item" aria-current="page">%s</li>',
                    esc_html($label)
                );
            }
        }

        if ($items_html !== '') {
            $breadcrumb_html = sprintf(
                '<nav class="service-hero-breadcrumb" aria-label="%1$s"><ol class="service-hero-breadcrumb-list">%2$s</ol></nav>',
                esc_attr__('Breadcrumb', 'intercargo-vite'),
                $items_html
            );

            // The stored inner paragraph is an editor preview only. Frontend output
            // is always replaced by the real WordPress page hierarchy, including on
            // pages saved by older theme versions with a hard-coded breadcrumb.
            $pattern = '#(<div\b[^>]*class="[^"]*\bservice-hero-breadcrumb-wrap\b[^"]*"[^>]*>).*?(</div>)#si';
            $replaced = preg_replace_callback(
                $pattern,
                static function (array $matches) use ($breadcrumb_html): string {
                    return $matches[1] . $breadcrumb_html . $matches[2];
                },
                $service_hero_content,
                1
            );
            if (is_string($replaced)) {
                $service_hero_content = $replaced;
            }
        }
    }
}

intercargo_render_native_section('service-hero', $service_hero_content, $block ?? null);
