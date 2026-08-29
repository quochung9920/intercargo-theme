<?php
/** One semantic whole-row Service Option link. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

intercargo_render_linked_native_item('service-option', $attributes ?? [], $content ?? '');
