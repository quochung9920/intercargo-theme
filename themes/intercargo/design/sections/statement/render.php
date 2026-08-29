<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$legacy = is_array($attributes['data'] ?? null) ? $attributes['data'] : [];

$read_text = static function (string $native_key, string $legacy_key, string $fallback) use ($attributes, $legacy): string {
    if (array_key_exists($native_key, $attributes)) {
        return trim(wp_strip_all_tags((string) $attributes[$native_key]));
    }
    if (array_key_exists($legacy_key, $legacy)) {
        return trim(wp_strip_all_tags((string) $legacy[$legacy_key]));
    }
    return $fallback;
};

$read_id = static function (string $native_key, string $legacy_key) use ($attributes, $legacy): int {
    $value = array_key_exists($native_key, $attributes)
        ? $attributes[$native_key]
        : ($legacy[$legacy_key] ?? 0);
    return is_numeric($value) ? max(0, (int) $value) : 0;
};

$read_url = static function (string $native_key) use ($attributes): string {
    if (! array_key_exists($native_key, $attributes) || ! is_string($attributes[$native_key])) {
        return '';
    }
    return esc_url_raw($attributes[$native_key]);
};

$line_one = $read_text('lineOne', 'line_one', 'Thirteen services.');
$line_two = $read_text('lineTwo', 'line_two', 'Five locations.');
$line_three = $read_text('lineThree', 'line_three', 'One team accountable.');

$band_image_id = $read_id('bandImageId', 'band_image');
$band_image = $read_url('bandImageUrl');
if ($band_image === '' && $band_image_id > 0) {
    $resolved = wp_get_attachment_image_url($band_image_id, 'full');
    $band_image = is_string($resolved) ? $resolved : '';
}
if ($band_image === '') {
    $asset_path = __DIR__ . '/assets/statement-band.png';
    $theme_root = rtrim(wp_normalize_path(get_theme_file_path()), '/');
    $package_dir = wp_normalize_path(__DIR__);
    $package_relative = ltrim(substr($package_dir, strlen($theme_root)), '/');
    $asset_url = get_theme_file_uri($package_relative . '/assets/statement-band.png');
    if (is_file($asset_path)) {
        $fingerprint = hash_file('sha256', $asset_path);
        if (is_string($fingerprint) && $fingerprint !== '') {
            $asset_url = add_query_arg('ver', substr($fingerprint, 0, 16), $asset_url);
        }
    }
    $band_image = $asset_url;
}

$requested_anchor = $read_text('sectionAnchor', 'section_anchor', '');
$base_id = sanitize_html_class($requested_anchor, 'statement');
$base_id = $base_id !== '' ? $base_id : 'statement';
$section_id = function_exists('intercargo_section_id')
    ? intercargo_section_id($base_id)
    : $base_id;
$title_id = $section_id . '-title';

$background_image_id = $read_id('backgroundImageId', 'background_image');
$background_image = $read_url('backgroundImageUrl');
if ($background_image === '' && $background_image_id > 0) {
    $resolved_background = wp_get_attachment_image_url($background_image_id, 'full');
    $background_image = is_string($resolved_background) ? $resolved_background : '';
}

$section_attrs = sprintf(
    'id="%s" data-section="statement"',
    esc_attr($section_id)
);
if ($background_image !== '') {
    $section_attrs .= ' style="background-image:url(' . esc_url($background_image) . ')"';
}

$accessible_label = trim($line_one . ' ' . $line_two . ' ' . $line_three);
?>
<section class="wp-block-acf-intercargo-statement statement-section" <?php echo $section_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="<?php echo esc_attr($title_id); ?>"><div class="band-media" aria-hidden="true"><img src="<?php echo esc_url($band_image); ?>" alt=""></div><div class="container statement-content"><h2 class="statement-title" id="<?php echo esc_attr($title_id); ?>" aria-label="<?php echo esc_attr($accessible_label); ?>"><span class="statement-mobile" aria-hidden="true"><span class="line"><?php echo esc_html($line_one); ?></span><span class="line"><?php echo esc_html($line_two); ?></span><span class="line"><?php echo esc_html($line_three); ?></span></span><span class="statement-desktop" aria-hidden="true"><span class="line"><?php echo esc_html(trim($line_one . ' ' . $line_two)); ?></span><span class="line"><?php echo esc_html($line_three); ?></span></span></h2></div></section>
