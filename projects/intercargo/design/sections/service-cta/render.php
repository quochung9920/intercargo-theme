<?php
/** Service CTA Band — frontend render. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('service-cta', $content ?? '', $block ?? null);
