<?php
/**
 * Page-level shell settings.
 *
 * These settings belong to the page instance, not to Gutenberg section content.
 * They control global shell behaviour such as the header variant and announcement
 * visibility without coupling those decisions to page templates or section blocks.
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const INTERCARGO_META_TRANSPARENT_HEADER = '_intercargo_transparent_header';
const INTERCARGO_META_HIDE_ANNOUNCEMENT = '_intercargo_hide_announcement';

function intercargo_register_page_shell_meta(): void
{
    $common = [
        'single' => true,
        'type' => 'boolean',
        'default' => false,
        'show_in_rest' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'auth_callback' => static function (): bool {
            return current_user_can('edit_pages');
        },
    ];

    register_post_meta('page', INTERCARGO_META_TRANSPARENT_HEADER, $common);
    register_post_meta('page', INTERCARGO_META_HIDE_ANNOUNCEMENT, $common);
}
add_action('init', 'intercargo_register_page_shell_meta', 9);

/**
 * Preserve the approved homepage shell when upgrading an existing site.
 *
 * Prior versions had a transparent overlay header globally and no announcement bar.
 * 4.7.0 changes the defaults to white header + visible announcement, so the existing
 * static front page is explicitly migrated once rather than visually changing on
 * theme update. Existing explicit metadata is never overwritten.
 */
function intercargo_migrate_page_shell_defaults_470(): void
{
    if (get_option('intercargo_page_shell_migrated_470')) {
        return;
    }

    $front_page_id = (int) get_option('page_on_front', 0);
    if ($front_page_id > 0 && get_post_type($front_page_id) === 'page') {
        if (! metadata_exists('post', $front_page_id, INTERCARGO_META_TRANSPARENT_HEADER)) {
            add_post_meta($front_page_id, INTERCARGO_META_TRANSPARENT_HEADER, true, true);
        }
        if (! metadata_exists('post', $front_page_id, INTERCARGO_META_HIDE_ANNOUNCEMENT)) {
            add_post_meta($front_page_id, INTERCARGO_META_HIDE_ANNOUNCEMENT, true, true);
        }
    }

    update_option('intercargo_page_shell_migrated_470', 1, false);
}
add_action('init', 'intercargo_migrate_page_shell_defaults_470', 30);

function intercargo_current_shell_page_id(): int
{
    if (! is_singular('page')) {
        return 0;
    }
    return (int) get_queried_object_id();
}

function intercargo_page_uses_transparent_header(?int $post_id = null): bool
{
    $post_id = $post_id ?? intercargo_current_shell_page_id();
    return $post_id > 0 && (bool) get_post_meta($post_id, INTERCARGO_META_TRANSPARENT_HEADER, true);
}

function intercargo_page_hides_announcement(?int $post_id = null): bool
{
    $post_id = $post_id ?? intercargo_current_shell_page_id();
    return $post_id > 0 && (bool) get_post_meta($post_id, INTERCARGO_META_HIDE_ANNOUNCEMENT, true);
}

function intercargo_page_shell_body_classes(array $classes): array
{
    $classes[] = intercargo_page_uses_transparent_header()
        ? 'intercargo-header-transparent'
        : 'intercargo-header-light';
    $classes[] = intercargo_page_hides_announcement()
        ? 'intercargo-announcement-hidden'
        : 'intercargo-announcement-visible';
    return array_values(array_unique($classes));
}
add_filter('body_class', 'intercargo_page_shell_body_classes');

function intercargo_enqueue_page_settings_editor_assets(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (! $screen || $screen->base !== 'post' || $screen->post_type !== 'page') {
        return;
    }

    $path = get_theme_file_path('inc/page-settings-editor.js');
    if (! is_file($path)) {
        return;
    }

    wp_enqueue_script(
        'intercargo-page-settings-editor',
        get_theme_file_uri('inc/page-settings-editor.js'),
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n'],
        (string) filemtime($path),
        true
    );
}
add_action('enqueue_block_editor_assets', 'intercargo_enqueue_page_settings_editor_assets', 30);

function intercargo_enqueue_header_variant_style(): void
{
    $path = get_theme_file_path('design/header/header.css');
    if (! is_file($path)) {
        return;
    }
    wp_enqueue_style(
        'intercargo-header-variants',
        get_theme_file_uri('design/header/header.css'),
        ['intercargo-global'],
        (string) filemtime($path)
    );
}
add_action('wp_enqueue_scripts', 'intercargo_enqueue_header_variant_style', 20);
