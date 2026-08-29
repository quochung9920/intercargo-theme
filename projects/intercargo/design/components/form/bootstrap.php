<?php
declare(strict_types=1);
if (! defined('ABSPATH')) exit;

/** Shared fallback styles for provider markup and editor previews. */
function intercargo_form_register_styles(): void {
    $base = __DIR__;
    $style = $base . '/style.css';
    $editor = $base . '/editor.css';

    if (! wp_style_is('intercargo-form-adapter', 'registered')) {
        wp_register_style(
            'intercargo-form-adapter',
            intercargo_theme_file_uri_from_path($style),
            [],
            is_file($style) ? substr(hash_file('sha256', $style) ?: '1', 0, 16) : null
        );
    }
    if (! wp_style_is('intercargo-form-editor-preview', 'registered')) {
        wp_register_style(
            'intercargo-form-editor-preview',
            intercargo_theme_file_uri_from_path($editor),
            ['intercargo-form-adapter'],
            is_file($editor) ? substr(hash_file('sha256', $editor) ?: '1', 0, 16) : null
        );
    }
}
intercargo_form_register_styles();

function intercargo_form_editor_script(): void {
    if (wp_script_is('intercargo-form-editor', 'registered')) return;
    $path = __DIR__ . '/editor.js';
    wp_register_script(
        'intercargo-form-editor',
        intercargo_theme_file_uri_from_path($path),
        ['wp-api-fetch','wp-blocks','wp-block-editor','wp-components','wp-element','wp-i18n','wp-server-side-render'],
        is_file($path) ? substr(hash_file('sha256', $path) ?: '1', 0, 16) : null,
        true
    );
    wp_add_inline_script('intercargo-form-editor', 'window.intercargoFormConfig=' . wp_json_encode([
        'restPath' => '/intercargo/v1/forms',
    ]) . ';', 'before');
}
intercargo_form_editor_script();

function intercargo_form_provider_catalog(): array {
    $providers = [];
    if (class_exists('WPCF7_ContactForm') && shortcode_exists('contact-form-7')) {
        $forms = [];
        $posts = get_posts(['post_type' => 'wpcf7_contact_form', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        foreach ($posts as $post) $forms[] = ['id' => (string) $post->ID, 'title' => get_the_title($post) ?: ('Form ' . $post->ID)];
        $providers[] = ['id' => 'cf7', 'title' => 'Contact Form 7', 'forms' => $forms];
    }
    if (class_exists('GFAPI')) {
        $forms = [];
        foreach ((array) GFAPI::get_forms(true, false, 'title', 'ASC') as $form) {
            if (! is_array($form) || empty($form['id'])) continue;
            $forms[] = ['id' => (string) $form['id'], 'title' => (string) ($form['title'] ?? ('Form ' . $form['id']))];
        }
        $providers[] = ['id' => 'gravity', 'title' => 'Gravity Forms', 'forms' => $forms];
    }
    return $providers;
}
function intercargo_form_register_rest(): void {
    register_rest_route('intercargo/v1', '/forms', [
        'methods' => 'GET',
        'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
        'callback' => static fn() => rest_ensure_response(['providers' => intercargo_form_provider_catalog()]),
    ]);
}
add_action('rest_api_init', 'intercargo_form_register_rest');


/**
 * Gravity Forms requires its assets to be queued before wp_head. Scan the
 * current post's saved block tree on the `wp` hook so package rendering can
 * remain completely local while Gravity still receives its normal runtime.
 */
function intercargo_form_collect_gravity_ids(array $blocks): array {
    $ids = [];
    foreach ($blocks as $block) {
        if (! is_array($block)) continue;
        $name = (string) ($block['blockName'] ?? '');
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        if (
            in_array($name, ['intercargo/form', 'intercargo/hero-email-form', 'intercargo/guide-email-form', 'intercargo/enquiry', 'acf/intercargo-enquiry'], true)
            && sanitize_key((string) ($attrs['provider'] ?? '')) === 'gravity'
        ) {
            $id = (int) ($attrs['formId'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $ids = array_merge($ids, intercargo_form_collect_gravity_ids($block['innerBlocks']));
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
}
function intercargo_form_prepare_gravity_assets(): void {
    if (is_admin() || ! function_exists('gravity_form_enqueue_scripts') || ! function_exists('parse_blocks')) return;
    $post = get_queried_object();
    if (! ($post instanceof WP_Post) || ! is_string($post->post_content) || $post->post_content === '') return;
    foreach (intercargo_form_collect_gravity_ids(parse_blocks($post->post_content)) as $form_id) {
        gravity_form_enqueue_scripts($form_id, true);
    }
}
add_action('wp', 'intercargo_form_prepare_gravity_assets', 20);

function intercargo_form_render_provider(string $provider, string $form_id, string $legacy_shortcode = ''): string {
    $provider = sanitize_key($provider); $form_id = trim($form_id);
    if ($provider === 'cf7' && $form_id !== '' && class_exists('WPCF7_ContactForm') && shortcode_exists('contact-form-7')) {
        $form = WPCF7_ContactForm::get_instance($form_id);
        if ($form instanceof WPCF7_ContactForm) {
            $markup = do_shortcode('[contact-form-7 id="' . (int) $form_id . '"]');
            return is_string($markup) ? $markup : '';
        }
    }
    if ($provider === 'gravity' && $form_id !== '' && function_exists('gravity_form')) {
        $id = (int) $form_id;
        if ($id > 0) {
            if (function_exists('gravity_form_enqueue_scripts')) gravity_form_enqueue_scripts($id, true);
            ob_start();
            gravity_form($id, false, false, false, null, true, 0, true);
            return (string) ob_get_clean();
        }
    }
    if ($legacy_shortcode !== '' && function_exists('intercargo_validate_cf7_shortcode')) {
        $validated = intercargo_validate_cf7_shortcode($legacy_shortcode);
        if ($validated !== null && shortcode_exists('contact-form-7')) {
            $markup = do_shortcode($validated);
            return is_string($markup) ? $markup : '';
        }
    }
    return '';
}
function intercargo_form_wrapper(string $variant, string $provider): array {
    return match ($variant) {
        'hero' => ['hero-form', ''],
        'guide' => ['guide-capture', ''],
        'enquiry' => ['intercargo-form-shell intercargo-form-shell--' . ($provider === 'gravity' ? 'gravity' : 'contact-form-7'), 'Enquiry form'],
        'quick-enquiry' => ['intercargo-form-shell intercargo-form-shell--quick-enquiry', 'Quick import enquiry'],
        default => ['intercargo-form-shell intercargo-form-shell--' . ($provider === 'gravity' ? 'gravity' : 'contact-form-7'), 'Form'],
    };
}
function intercargo_render_quick_enquiry_form(): string {
    $field_id = wp_unique_id('intercargo-quick-enquiry-');
    return '<form class="intercargo-quick-enquiry" action="#about-enquiry" method="get">'
        . '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">What are you importing?</label>'
        . '<input id="' . esc_attr($field_id) . '" name="q" type="text" placeholder="Roughly 14 cubic metres from Ningbo">'
        . '<button type="submit">Send it</button>'
        . '</form>';
}
function intercargo_render_form_adapter(array $attributes, ?string $fixed_variant = null): string {
    $provider = sanitize_key((string) ($attributes['provider'] ?? ''));
    $form_id = trim((string) ($attributes['formId'] ?? ''));
    $legacy = trim((string) ($attributes['shortcode'] ?? $attributes['legacyShortcode'] ?? ''));
    $variant = $fixed_variant ?? sanitize_key((string) ($attributes['variant'] ?? 'default'));
    $label = trim(wp_strip_all_tags((string) ($attributes['label'] ?? '')));
    $note = trim(wp_strip_all_tags((string) ($attributes['note'] ?? '')));
    $markup = intercargo_form_render_provider($provider, $form_id, $legacy);
    if ($markup === '' && $provider === '' && $form_id === '' && $legacy === '') {
        if ($variant === 'hero' && function_exists('intercargo_render_hero_email_form')) {
            $markup = intercargo_render_hero_email_form('');
        } elseif ($variant === 'guide' && function_exists('intercargo_render_guide_email_form')) {
            $markup = intercargo_render_guide_email_form('');
        } elseif ($variant === 'enquiry' && function_exists('intercargo_render_enquiry_form')) {
            $markup = intercargo_render_enquiry_form('');
        } elseif ($variant === 'quick-enquiry') {
            $markup = intercargo_render_quick_enquiry_form();
        }
    }
    if ($markup === '' && ! is_admin()) return '';
    if ($variant === 'bare') {
        return $markup !== '' ? $markup : '<div class="intercargo-form-shell__placeholder">Choose a form in the block settings.</div>';
    }
    [$class, $default_label] = intercargo_form_wrapper($variant, $provider);
    if ($label === '') $label = $default_label;
    $body = $markup !== '' ? $markup : '<div class="intercargo-form-shell__placeholder">Choose a form in the block settings.</div>';
    if ($note !== '') $body .= '<p class="form-note">' . esc_html($note) . '</p>';
    return '<div class="' . esc_attr($class) . '"' . ($label !== '' ? ' aria-label="' . esc_attr($label) . '"' : '') . '>' . $body . '</div>';
}
