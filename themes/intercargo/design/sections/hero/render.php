<?php
/**
 * Hero — frontend render.
 *
 * The shell and the gate are shared; the section identity comes from template.json.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('hero', $content ?? '', $block ?? null);
