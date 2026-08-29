<?php
/** Global announcement / live world clocks. */
declare(strict_types=1);

if (! defined('ABSPATH')) exit;

function intercargo_world_clock_items(): array
{
    $items = [
        ['label' => __('Australia', 'intercargo-vite'), 'timezone' => 'Australia/Sydney'],
        ['label' => __('China', 'intercargo-vite'), 'timezone' => 'Asia/Shanghai'],
        ['label' => __('US', 'intercargo-vite'), 'timezone' => 'America/Los_Angeles'],
        ['label' => __('Singapore', 'intercargo-vite'), 'timezone' => 'Asia/Singapore'],
        ['label' => __('Japan', 'intercargo-vite'), 'timezone' => 'Asia/Tokyo'],
        ['label' => __('South Korea', 'intercargo-vite'), 'timezone' => 'Asia/Seoul'],
    ];

    $filtered = apply_filters('intercargo_world_clock_items', $items);
    if (! is_array($filtered)) return $items;

    $valid = array_flip(timezone_identifiers_list());
    $clean = [];
    foreach ($filtered as $item) {
        if (! is_array($item)) continue;
        $label = sanitize_text_field((string) ($item['label'] ?? ''));
        $timezone = (string) ($item['timezone'] ?? '');
        if ($label === '' || ! isset($valid[$timezone])) continue;
        $clean[] = ['label' => $label, 'timezone' => $timezone];
    }
    return $clean;
}

function intercargo_world_clock_time(string $timezone): string
{
    try {
        $zone = new DateTimeZone($timezone);
        return (new DateTimeImmutable('now', $zone))->format('g:i a');
    } catch (Throwable $error) {
        return '';
    }
}

function intercargo_should_show_announcement(): bool
{
    return ! function_exists('intercargo_page_hides_announcement') || ! intercargo_page_hides_announcement();
}

function intercargo_render_announcement(): void
{
    if (! intercargo_should_show_announcement()) return;
    $file = get_theme_file_path('design/components/announcement/render.php');
    if (is_file($file)) require $file;
}

function intercargo_enqueue_announcement_assets(): void
{
    if (! intercargo_should_show_announcement()) return;

    $style = get_theme_file_path('design/components/announcement/style.css');
    $script = get_theme_file_path('design/components/announcement/view.js');

    if (is_file($style)) {
        wp_enqueue_style(
            'intercargo-announcement',
            get_theme_file_uri('design/components/announcement/style.css'),
            ['intercargo-global'],
            substr(hash_file('sha256', $style) ?: '1', 0, 16)
        );
    }
    if (is_file($script)) {
        wp_enqueue_script(
            'intercargo-announcement',
            get_theme_file_uri('design/components/announcement/view.js'),
            [],
            substr(hash_file('sha256', $script) ?: '1', 0, 16),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'intercargo_enqueue_announcement_assets', 21);
