<?php
/** Content Split — frontend render. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('content-split', $content ?? '', $block ?? null);
