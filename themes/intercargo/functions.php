<?php
/** Theme bootstrap. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once get_theme_file_path('inc/assets.php');
require_once get_theme_file_path('inc/section-runtime.php');
require_once get_theme_file_path('inc/content-policy.php');
require_once get_theme_file_path('inc/composition.php');
require_once get_theme_file_path('inc/section-render.php');
require_once get_theme_file_path('inc/hero-email-form.php');
require_once get_theme_file_path('inc/brand.php');
require_once get_theme_file_path('inc/page-settings.php');
require_once get_theme_file_path('inc/announcement.php');
require_once get_theme_file_path('inc/block-packages.php');
require_once get_theme_file_path('inc/shared-sections.php');
require_once get_theme_file_path('inc/legacy-block-data.php');
require_once get_theme_file_path('inc/migrations-4112.php');
require_once get_theme_file_path('inc/legacy-block-compat.php');
require_once get_theme_file_path('inc/vendor-libraries.php');
require_once get_theme_file_path('inc/ui-components.php');

function intercargo_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    register_nav_menus([
        'primary' => __('Primary Navigation', 'intercargo-vite'),
        'footer_services' => __('Footer — Services', 'intercargo-vite'),
        'footer_locations' => __('Footer — Locations', 'intercargo-vite'),
        'footer_company' => __('Footer — Company', 'intercargo-vite'),
    ]);
}
add_action('after_setup_theme', 'intercargo_theme_setup');

/**
 * WordPress 7.1's classic-theme template enhancement corrupts this document when
 * it hoists many custom block styles: closing HEAD/BODY shell markup and the final
 * stylesheets disappear. Templates pre-render blocks before wp_head, so opt out
 * through the core's documented filter instead of modifying WordPress itself.
 */
function intercargo_disable_frontend_template_enhancement_buffer(bool $should_buffer): bool
{
    return is_admin() ? $should_buffer : false;
}
add_filter(
    'wp_should_output_buffer_template_for_enhancement',
    'intercargo_disable_frontend_template_enhancement_buffer',
    10
);

function intercargo_register_block_category(array $categories): array
{
    array_unshift(
        $categories,
        ['slug' => 'intercargo-sections', 'title' => __('Intercargo — Sections', 'intercargo-vite')],
        ['slug' => 'intercargo-section-items', 'title' => __('Intercargo — Section Items', 'intercargo-vite')]
    );
    return $categories;
}
add_filter('block_categories_all', 'intercargo_register_block_category');

/**
 * Register the *pattern* category.
 *
 * `block_categories_all` above registers the block category. Theme patterns in
 * `patterns/*.php` declare `Categories: intercargo-sections`, which is a pattern
 * category — a separate registry. WordPress auto-registers theme patterns on
 * `init` at the default priority, so this must run before that.
 */
function intercargo_register_pattern_category(): void
{
    if (! function_exists('register_block_pattern_category')) {
        return;
    }
    register_block_pattern_category('intercargo-sections', [
        'label' => __('Intercargo Sections', 'intercargo-vite'),
        'description' => __('Reviewed Intercargo page sections.', 'intercargo-vite'),
    ]);
}
add_action('init', 'intercargo_register_pattern_category', 9);

/**
 * Section and component blocks are registered exclusively by inc/block-packages.php.
 * One package folder is the registry; no ACF or central slug list is involved.
 */

add_action('wp_enqueue_scripts', 'intercargo_enqueue_theme_assets');

/*
 * `enqueue_block_assets` runs inside the iframed editor canvas as well as on the
 * frontend. `enqueue_block_editor_assets` does not: it targets the surrounding
 * wp-admin document, where the site stylesheet would restyle the admin UI while
 * still never reaching the canvas.
 */
add_action('enqueue_block_assets', 'intercargo_enqueue_block_assets');

/*
 * Scripts belong in the wp-admin document, not the canvas iframe: format types
 * are registered against the editor's JavaScript runtime, which lives outside it.
 */
add_action('enqueue_block_editor_assets', 'intercargo_enqueue_editor_assets');
