<?php
/**
 * Locations — frontend render.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('locations', $content ?? '', $block ?? null);
