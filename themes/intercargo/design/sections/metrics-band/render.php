<?php
/** Metrics Band — frontend render. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('metrics-band', $content ?? '', $block ?? null);
