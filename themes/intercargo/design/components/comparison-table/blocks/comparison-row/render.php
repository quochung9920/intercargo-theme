<?php
/** Comparison Row editor/content fallback renderer. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_item('comparison-row', $content ?? '');
