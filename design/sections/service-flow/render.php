<?php
/** Generic Service Flow section renderer. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('service-flow', $content ?? '', $block ?? null);
