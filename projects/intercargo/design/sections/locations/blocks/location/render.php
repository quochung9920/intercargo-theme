<?php
/**
 * One facility row.
 *
 * A pure wrapper. Validation belongs to the parent section: its gate walks the whole
 * stored tree including these rows, and a rejected tree renders nothing at all, so a
 * row can never reach the page outside a section that has already passed.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_item('location', $content ?? '');
