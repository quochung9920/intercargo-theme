<?php
/** Read old ACF block comment data after a native package takes over the block name. */
declare(strict_types=1);
if (! defined('ABSPATH')) exit;

function intercargo_legacy_attribute_data(array $attributes): array {
    return is_array($attributes['data'] ?? null) ? $attributes['data'] : [];
}
function intercargo_native_or_legacy_text(array $attributes, string $native, string $legacy, string $fallback = ''): string {
    if (array_key_exists($native, $attributes) && is_scalar($attributes[$native])) return trim(wp_strip_all_tags((string) $attributes[$native]));
    $data = intercargo_legacy_attribute_data($attributes);
    if (array_key_exists($legacy, $data) && is_scalar($data[$legacy])) return trim(wp_strip_all_tags((string) $data[$legacy]));
    return $fallback;
}
function intercargo_native_or_legacy_id(array $attributes, string $native, string $legacy): int {
    if (array_key_exists($native, $attributes) && is_numeric($attributes[$native])) return max(0, (int) $attributes[$native]);
    $data = intercargo_legacy_attribute_data($attributes);
    return isset($data[$legacy]) && is_numeric($data[$legacy]) ? max(0, (int) $data[$legacy]) : 0;
}
function intercargo_native_or_legacy_rows(array $attributes, string $native, string $legacy, array $fields, array $fallback): array {
    if (array_key_exists($native, $attributes) && is_array($attributes[$native]) && $attributes[$native] !== []) {
        $source = $attributes[$native];
    } else {
        $data = intercargo_legacy_attribute_data($attributes);
        $source = [];
        if (isset($data[$legacy]) && is_array($data[$legacy])) {
            $source = $data[$legacy];
        } else {
            $count = isset($data[$legacy]) && is_numeric($data[$legacy]) ? max(0, (int) $data[$legacy]) : 0;
            if ($count === 0) {
                foreach (array_keys($data) as $key) {
                    if (preg_match('/^' . preg_quote($legacy, '/') . '_(\d+)_/', (string) $key, $m)) $count = max($count, ((int) $m[1]) + 1);
                }
            }
            for ($i = 0; $i < $count; $i++) {
                $row = [];
                foreach ($fields as $field) {
                    $key = $legacy . '_' . $i . '_' . $field;
                    if (array_key_exists($key, $data) && is_scalar($data[$key])) $row[$field] = (string) $data[$key];
                }
                if ($row !== []) $source[] = $row;
            }
        }
        if ($source === []) $source = $fallback;
    }
    $rows = [];
    foreach ($source as $row) {
        if (! is_array($row)) continue;
        $clean = [];
        foreach ($fields as $field) $clean[$field] = trim(wp_strip_all_tags((string) ($row[$field] ?? '')));
        $rows[] = $clean;
    }
    return $rows !== [] ? $rows : $fallback;
}
function intercargo_native_or_legacy_image_url(array $attributes, string $id_native, string $url_native, string $legacy): string {
    if (! empty($attributes[$url_native]) && is_string($attributes[$url_native])) return esc_url_raw($attributes[$url_native]);
    $id = intercargo_native_or_legacy_id($attributes, $id_native, $legacy);
    if ($id > 0) {
        $url = wp_get_attachment_image_url($id, 'full');
        if (is_string($url)) return $url;
    }
    return '';
}
function intercargo_native_section_attrs(array $attributes, string $default_id, string $slug): array {
    $anchor = intercargo_native_or_legacy_text($attributes, 'sectionAnchor', 'section_anchor', '');
    $base = sanitize_html_class($anchor, $default_id); if ($base === '') $base = $default_id;
    $id = intercargo_section_id($base);
    $background = intercargo_native_or_legacy_image_url($attributes, 'backgroundImageId', 'backgroundImageUrl', 'background_image');
    $attrs = sprintf('id="%s" data-section="%s"', esc_attr($id), esc_attr($slug));
    if ($background !== '') $attrs .= ' style="background-image:url(' . esc_url($background) . ')"';
    return [$id, $attrs];
}
