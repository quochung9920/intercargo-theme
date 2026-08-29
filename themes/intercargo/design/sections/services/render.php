<?php
/** Services — frontend render. */
declare(strict_types=1);
if (! defined('ABSPATH')) {
    exit;
}
intercargo_render_native_section('services', $content ?? '', $block ?? null);
