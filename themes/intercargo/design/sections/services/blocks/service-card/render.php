<?php
/**
 * Service card — one semantic whole-card anchor.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_linked_native_item('service-card', $attributes ?? [], $content ?? '');
