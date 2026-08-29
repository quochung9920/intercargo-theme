<?php
/**
 * Backward compatibility for historical ACF-era block IDs.
 *
 * These aliases do not use ACF. They exist only so old post_content can render
 * until the 3.9+/4.x lazy migration rewrites the block name to its canonical
 * `intercargo/*` package. New content can never insert an alias.
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

function intercargo_register_legacy_block_aliases(): void
{
    if (! class_exists('WP_Block_Type_Registry') || ! function_exists('register_block_type')) {
        return;
    }

    $registry = WP_Block_Type_Registry::get_instance();
    foreach (intercargo_block_package_legacy_name_map() as $legacy => $canonical) {
        if ($registry->is_registered($legacy)) {
            continue;
        }

        $canonical_type = $registry->get_registered($canonical);
        if (! $canonical_type || ! is_callable($canonical_type->render_callback)) {
            continue;
        }

        $render = $canonical_type->render_callback;
        register_block_type($legacy, [
            'api_version' => 3,
            'title' => sprintf(__('Legacy %s', 'intercargo-vite'), $canonical_type->title ?: $canonical),
            'category' => 'intercargo-sections',
            'attributes' => is_array($canonical_type->attributes) ? $canonical_type->attributes : [],
            'supports' => [
                'inserter' => false,
                'html' => false,
                'reusable' => false,
            ],
            'render_callback' => static function (array $attributes, string $content, WP_Block $block) use ($render): string {
                $result = $render($attributes, $content, $block);
                return is_string($result) ? $result : '';
            },
        ]);
    }
}
add_action('init', 'intercargo_register_legacy_block_aliases', 20);
