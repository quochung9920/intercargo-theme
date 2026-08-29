<?php
/** Generic Media Split section renderer. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_section('media-split', $content ?? '', $block ?? null);
