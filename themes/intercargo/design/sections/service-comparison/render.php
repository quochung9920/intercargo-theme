<?php
/** Service Comparison section renderer. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('service-comparison', $content ?? '', $block ?? null);
