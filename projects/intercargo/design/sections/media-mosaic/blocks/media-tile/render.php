<?php
/** One repeatable Media Tile item. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$config = intercargo_composition_item_config('media-tile');
if ($config === null || trim($content ?? '') === '') {
    return;
}

$span = static function (mixed $value, int $fallback): int {
    $number = (int) $value;
    return $number >= 1 && $number <= 12 ? $number : $fallback;
};

$span_mobile = $span($attributes['spanMobile'] ?? null, 12);
$span_tablet = $span($attributes['spanTablet'] ?? null, 4);
$span_desktop = $span($attributes['spanDesktop'] ?? null, 3);
$style = sprintf(
    '--media-mosaic-span-mobile:%d;--media-mosaic-span-tablet:%d;--media-mosaic-span-desktop:%d',
    $span_mobile,
    $span_tablet,
    $span_desktop
);

printf(
    '<%1$s class="%2$s" style="%3$s">%4$s</%1$s>',
    $config['element'],
    esc_attr($config['className']),
    esc_attr($style),
    $content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stored block content, validated by the parent section.
);
