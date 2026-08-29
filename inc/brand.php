<?php
/**
 * Brand panel — the client's global controls.
 *
 * This theme is a classic theme by decision D7, so the Site Editor's Global Styles
 * UI is unavailable. Rather than approximate it, the client gets one purpose-built
 * Customizer section covering exactly what changes over a site's life: brand colour,
 * primary/white logos, the header call to action, two footer strings, and an approved font pairing.
 *
 * What is deliberately NOT exposed: the 25 sizing tokens and their 78 clamp()
 * interpolations. That system is what keeps the design correct at 390, 768 and
 * 1440 pixels. Exposing it would expose the ability to destroy it.
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Colours the client may change.
 *
 * The supporting neutrals (--mobile-paper, --mobile-line, --muted, --muted-light,
 * --footer-copy) stay fixed: they are greys that do not move when a brand shifts
 * its accent, and every one of them exposed is another way to break contrast.
 */
function intercargo_brand_defaults(): array
{
    return [
        'ink'    => '#131417',
        'paper'  => '#f4f3f0',
        'yellow' => '#fbc925',
        'blue'   => '#5170ff',
        'line'   => '#e4e2dc',
    ];
}

/**
 * Approved typeface pairings (ADR D8).
 *
 * A closed list, not free choice. Every pairing here resolves to fonts already
 * bundled with the theme, or to the visitor's system stack; adding a pairing means
 * bundling its woff2 files, because the theme makes zero remote font requests.
 */
function intercargo_font_pairings(): array
{
    return [
        'geist-poppins' => [
            'label'   => __('Geist + Poppins (design default)', 'intercargo-vite'),
            'heading' => '"Geist", "Poppins", Arial, sans-serif',
            'body'    => '"Poppins", Arial, sans-serif',
        ],
        'poppins-only' => [
            'label'   => __('Poppins throughout', 'intercargo-vite'),
            'heading' => '"Poppins", Arial, sans-serif',
            'body'    => '"Poppins", Arial, sans-serif',
        ],
        'system' => [
            'label'   => __('System sans (no webfont)', 'intercargo-vite'),
            'heading' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif',
            'body'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif',
        ],
    ];
}

function intercargo_brand_text_defaults(): array
{
    return [
        'cta_label'           => __('Talk to our team', 'intercargo-vite'),
        'cta_url'             => '#enquiry',
        'cta_secondary_label' => __('Start the conversation', 'intercargo-vite'),
        'footer_intro'        => __('Freight forwarding, customs and 3PL warehousing for businesses importing into Australia.', 'intercargo-vite'),
        'legal_line'          => '',
    ];
}

/* -------------------------------------------------------------------------- */
/* Readers                                                                     */
/* -------------------------------------------------------------------------- */

function intercargo_brand_color(string $name): string
{
    $defaults = intercargo_brand_defaults();
    if (! array_key_exists($name, $defaults)) {
        return '';
    }
    $stored = get_theme_mod('intercargo_' . $name, $defaults[$name]);
    $clean = is_string($stored) ? sanitize_hex_color($stored) : null;
    return $clean ?? $defaults[$name];
}

function intercargo_brand_font_pairing(): array
{
    $pairings = intercargo_font_pairings();
    $key = (string) get_theme_mod('intercargo_font_pairing', 'geist-poppins');
    return array_key_exists($key, $pairings) ? $pairings[$key] : $pairings['geist-poppins'];
}

function intercargo_brand_text(string $key): string
{
    $defaults = intercargo_brand_text_defaults();
    if (! array_key_exists($key, $defaults)) {
        return '';
    }
    $stored = get_theme_mod('intercargo_' . $key, $defaults[$key]);
    $stored = is_string($stored) ? trim($stored) : '';
    if ($key === 'legal_line' && $stored === 'ABN and licence details publish at launch.') $stored = '';
    return $stored !== '' ? $stored : $defaults[$key];
}

function intercargo_brand_cta_url(): string
{
    $stored = (string) get_theme_mod('intercargo_cta_url', '#enquiry');
    return intercargo_is_allowed_url($stored) ? $stored : '#enquiry';
}

/**
 * Resolve a Customizer media setting to a URL, with a packaged theme fallback.
 */
function intercargo_brand_media_url(string $setting, string $fallback_asset): string
{
    $attachment = (int) get_theme_mod($setting, 0);
    if ($attachment > 0) {
        $url = wp_get_attachment_image_url($attachment, 'full');
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }
    return intercargo_asset_url($fallback_asset);
}

/**
 * Primary logo for white/light header surfaces.
 */
function intercargo_brand_primary_logo_url(): string
{
    return intercargo_brand_media_url(
        'intercargo_primary_logo',
        'bf2c1794c4a816543d7f0cbb584ee4432ddc42e8.png'
    );
}

/**
 * White/light-on-dark logo for transparent headers, the mobile menu and footer.
 *
 * Before 4.7.1 the theme exposed one `intercargo_logo` setting. Keep reading that
 * value as a compatibility fallback so existing sites do not lose their homepage
 * logo during the upgrade.
 */
function intercargo_brand_white_logo_url(): string
{
    $attachment = (int) get_theme_mod('intercargo_white_logo', 0);
    if ($attachment > 0) {
        $url = wp_get_attachment_image_url($attachment, 'full');
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    $legacy_attachment = (int) get_theme_mod('intercargo_logo', 0);
    if ($legacy_attachment > 0) {
        $legacy_url = wp_get_attachment_image_url($legacy_attachment, 'full');
        if (is_string($legacy_url) && $legacy_url !== '') {
            return $legacy_url;
        }
    }

    return intercargo_asset_url('9647fc50a6355288d10613908d79cbffabf2463a.png');
}

/**
 * Backwards-compatible alias used by the dark footer/mobile menu code.
 */
function intercargo_brand_logo_url(): string
{
    return intercargo_brand_white_logo_url();
}

/**
 * Migrate the historical single Logo control into the new White logo slot once.
 * The old theme only used that setting on dark/transparent surfaces, so this keeps
 * the approved homepage branding intact while introducing an independent primary
 * logo for white headers.
 */
function intercargo_migrate_logo_settings_471(): void
{
    if (get_option('intercargo_brand_logos_migrated_471')) {
        return;
    }

    $legacy = (int) get_theme_mod('intercargo_logo', 0);
    $white = (int) get_theme_mod('intercargo_white_logo', 0);
    if ($white <= 0 && $legacy > 0) {
        set_theme_mod('intercargo_white_logo', $legacy);
    }

    update_option('intercargo_brand_logos_migrated_471', 1, false);
}
add_action('init', 'intercargo_migrate_logo_settings_471', 25);

/* -------------------------------------------------------------------------- */
/* Customizer                                                                  */
/* -------------------------------------------------------------------------- */

function intercargo_sanitize_font_pairing(string $value): string
{
    return array_key_exists($value, intercargo_font_pairings()) ? $value : 'geist-poppins';
}

function intercargo_sanitize_brand_url(string $value): string
{
    return intercargo_is_allowed_url($value) ? esc_url_raw($value) : '#enquiry';
}

function intercargo_customize_register($wp_customize): void
{
    $wp_customize->add_section('intercargo_brand', [
        'title'       => __('Intercargo Brand', 'intercargo-vite'),
        'description' => __('Colour, logo, call to action and typeface. Layout and spacing are fixed by the design system.', 'intercargo-vite'),
        'priority'    => 20,
    ]);

    $labels = [
        'ink'    => __('Ink (text and dark surfaces)', 'intercargo-vite'),
        'paper'  => __('Paper (page background)', 'intercargo-vite'),
        'yellow' => __('Accent (primary action)', 'intercargo-vite'),
        'blue'   => __('Link and focus', 'intercargo-vite'),
        'line'   => __('Rules and borders', 'intercargo-vite'),
    ];
    foreach (intercargo_brand_defaults() as $name => $default) {
        $wp_customize->add_setting('intercargo_' . $name, [
            'default' => $default,
            'sanitize_callback' => 'sanitize_hex_color',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'intercargo_' . $name, [
            'label'   => $labels[$name],
            'section' => 'intercargo_brand',
        ]));
    }

    $wp_customize->add_setting('intercargo_primary_logo', [
        'default' => 0,
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'intercargo_primary_logo', [
        'label'       => __('Primary logo (white header)', 'intercargo-vite'),
        'description' => __('Used on the default white/light header. Leave blank to use the bundled dark Intercargo logo.', 'intercargo-vite'),
        'section'     => 'intercargo_brand',
        'mime_type'   => 'image',
    ]));

    $wp_customize->add_setting('intercargo_white_logo', [
        'default' => 0,
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'intercargo_white_logo', [
        'label'       => __('White logo (transparent header)', 'intercargo-vite'),
        'description' => __('Used on transparent/dark headers, the mobile menu and footer. Existing sites automatically inherit the previous Logo setting here.', 'intercargo-vite'),
        'section'     => 'intercargo_brand',
        'mime_type'   => 'image',
    ]));

    $text_controls = [
        'cta_label'           => __('Header button label', 'intercargo-vite'),
        'cta_secondary_label' => __('Mobile menu button label', 'intercargo-vite'),
        'footer_intro'        => __('Footer introduction', 'intercargo-vite'),
        'legal_line'          => __('Footer legal line', 'intercargo-vite'),
    ];
    $defaults = intercargo_brand_text_defaults();
    foreach ($text_controls as $key => $label) {
        $wp_customize->add_setting('intercargo_' . $key, [
            'default' => $defaults[$key],
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'refresh',
        ]);
        $wp_customize->add_control('intercargo_' . $key, [
            'label'   => $label,
            'section' => 'intercargo_brand',
            'type'    => $key === 'footer_intro' ? 'textarea' : 'text',
        ]);
    }

    $wp_customize->add_setting('intercargo_cta_url', [
        'default' => '#enquiry',
        'sanitize_callback' => 'intercargo_sanitize_brand_url',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('intercargo_cta_url', [
        'label'       => __('Header button link', 'intercargo-vite'),
        'description' => __('An on-page anchor, a relative path, or a full URL.', 'intercargo-vite'),
        'section'     => 'intercargo_brand',
        'type'        => 'text',
    ]);

    $choices = [];
    foreach (intercargo_font_pairings() as $key => $pairing) {
        $choices[$key] = $pairing['label'];
    }
    $wp_customize->add_setting('intercargo_font_pairing', [
        'default' => 'geist-poppins',
        'sanitize_callback' => 'intercargo_sanitize_font_pairing',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('intercargo_font_pairing', [
        'label'   => __('Typeface pairing', 'intercargo-vite'),
        'section' => 'intercargo_brand',
        'type'    => 'select',
        'choices' => $choices,
    ]);
}
add_action('customize_register', 'intercargo_customize_register');

/* -------------------------------------------------------------------------- */
/* Output                                                                      */
/* -------------------------------------------------------------------------- */

/**
 * The custom-property override.
 *
 * Every name here is declared in src/css/global.css; the stylesheet reads them and
 * nothing else needs to change when the client picks a new colour. The token names
 * are asserted against the stylesheet by tests/test_brand_panel_contract.py, so a
 * rename on either side fails the build rather than silently doing nothing.
 */
function intercargo_brand_css(): string
{
    $fonts = intercargo_brand_font_pairing();
    $hero_fallback_url = esc_url_raw(
        intercargo_asset_url('6ea7bb3c143f74e411d5b49ab617c6acb1fdbfea.png')
    );
    $hero_fallback = $hero_fallback_url === ''
        ? 'none'
        : sprintf('url("%s")', $hero_fallback_url);
    return sprintf(
        ':root{--ink: %s;--paper: %s;--yellow: %s;--blue: %s;--line: %s;--font-heading: %s;--font-body: %s;--hero-fallback-image: %s;}',
        intercargo_brand_color('ink'),
        intercargo_brand_color('paper'),
        intercargo_brand_color('yellow'),
        intercargo_brand_color('blue'),
        intercargo_brand_color('line'),
        $fonts['heading'],
        $fonts['body'],
        $hero_fallback
    );
}

function intercargo_print_brand_css(): void
{
    $css = wp_strip_all_tags(intercargo_brand_css());
    echo '<style id="intercargo-brand">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values come from closed font and sanitized colour allowlists.
}
add_action('wp_head', 'intercargo_print_brand_css', 20);

/**
 * Theme favicon fallback.
 *
 * WordPress owns the favicon when a Site Icon is configured. Staging installs
 * commonly have no Site Icon, which makes browsers request /favicon.ico and log
 * a 404. In that case declare the packaged SVG explicitly. This is presentation
 * fallback only; setting a Site Icon in wp-admin automatically takes precedence.
 */
function intercargo_print_fallback_favicon(): void
{
    if (function_exists('has_site_icon') && has_site_icon()) {
        return;
    }
    $url = intercargo_asset_url('favicon.svg');
    if ($url === '') {
        return;
    }
    $ico = intercargo_asset_url('favicon.ico');
    printf('<link rel="icon" href="%1$s" type="image/svg+xml">' . "\n", esc_url($url));
    if ($ico !== '') {
        printf('<link rel="alternate icon" href="%1$s" type="image/x-icon">' . "\n", esc_url($ico));
        printf('<link rel="shortcut icon" href="%1$s" type="image/x-icon">' . "\n", esc_url($ico));
    }
}
add_action('wp_head', 'intercargo_print_fallback_favicon', 2);

/**
 * Also satisfy browsers that probe `/favicon.ico` directly before parsing HEAD.
 * The request reaches WordPress on standard permalink/server configurations.
 */
function intercargo_serve_favicon_request(): void
{
    if (function_exists('has_site_icon') && has_site_icon()) {
        return;
    }
    $path = get_theme_file_path('assets/favicon.ico');
    if (! is_readable($path)) {
        return;
    }
    status_header(200);
    header('Content-Type: image/x-icon');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}
add_action('do_favicon', 'intercargo_serve_favicon_request', 1);

/**
 * The same override inside the editor canvas.
 *
 * Without this the editor shows the design's default palette while the site shows
 * the client's, and every colour judgement made in the editor is made against the
 * wrong background.
 */
function intercargo_enqueue_brand_canvas_css(): void
{
    if (! is_admin()) {
        return;
    }
    $global = intercargo_register_global_style();
    if ($global === null) {
        return;
    }
    wp_add_inline_style($global, intercargo_brand_css());
}
add_action('enqueue_block_assets', 'intercargo_enqueue_brand_canvas_css', 20);
