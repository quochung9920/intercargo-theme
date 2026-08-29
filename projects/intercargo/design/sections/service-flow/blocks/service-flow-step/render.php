<?php
/** One repeatable Service Flow step. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_native_item('service-flow-step', $content ?? '');
