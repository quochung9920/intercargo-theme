<?php
/** Strict Contact Form 7 adapters for theme form sections. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

function intercargo_get_cf7_shortcode_from_marker(string $option_name): string
{
    if (! class_exists('WPCF7_ContactForm') || ! shortcode_exists('contact-form-7')) {
        return '';
    }
    $marker = get_option($option_name, null);
    if (! is_array($marker) || empty($marker['id']) || empty($marker['hash'])) {
        return '';
    }
    $form = WPCF7_ContactForm::get_instance($marker['id']);
    if (! $form instanceof WPCF7_ContactForm) {
        return '';
    }
    $shortcode = $form->shortcode();
    return is_string($shortcode) ? trim($shortcode) : '';
}




function intercargo_validate_cf7_shortcode(string $shortcode): ?string
{
    $shortcode = trim($shortcode);
    if ($shortcode === '' || ! preg_match('/^\[contact-form-7(?:\s+[^\[\]]*)?\]$/', $shortcode)) {
        return null;
    }
    $attribute_source = trim(substr($shortcode, strlen('[contact-form-7'), -1));
    $attributes = shortcode_parse_atts($attribute_source);
    if (! is_array($attributes) || empty($attributes['id'])) {
        return null;
    }
    $allowed = ['id', 'title', 'html_id', 'html_class'];
    foreach ($attributes as $key => $value) {
        if (! is_string($key) || ! in_array($key, $allowed, true) || ! is_string($value)) {
            return null;
        }
    }
    if (! preg_match('/^[A-Za-z0-9_-]+$/', $attributes['id'])) {
        return null;
    }
    return $shortcode;
}

function intercargo_render_cf7_form(string $shortcode, string $fallback_option): string
{
    if (! shortcode_exists('contact-form-7')) {
        return '';
    }
    if (trim($shortcode) === '') {
        $shortcode = intercargo_get_cf7_shortcode_from_marker($fallback_option);
    }
    $shortcode = intercargo_validate_cf7_shortcode($shortcode);
    if ($shortcode === null) {
        return '';
    }
    $markup = do_shortcode($shortcode);
    return is_string($markup) ? $markup : '';
}

function intercargo_render_hero_email_form(string $shortcode = ''): string
{
    return intercargo_render_cf7_form($shortcode, '_intercargo_cf7_hero_email_v1');
}

function intercargo_render_guide_email_form(string $shortcode = ''): string
{
    return intercargo_render_cf7_form($shortcode, '_intercargo_cf7_guide_email_v1');
}

function intercargo_render_enquiry_form(string $shortcode = ''): string
{
    return intercargo_render_cf7_form($shortcode, '_intercargo_cf7_enquiry_v1');
}
