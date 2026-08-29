<?php
/** Ensure the Content Split stylesheet is available before wp_head. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

function intercargo_content_split_enqueue_front_style(): void {
    if (is_admin()) return;
    $path = __DIR__ . '/style.css';
    wp_enqueue_style('intercargo-content-split-front', get_theme_file_uri('design/sections/content-split/style.css'), ['intercargo-global'], is_file($path) ? substr(hash_file('sha256', $path) ?: '1', 0, 16) : null);
}
add_action('wp_enqueue_scripts', 'intercargo_content_split_enqueue_front_style', 20);
