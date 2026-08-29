<?php
/** Global world-clock announcement bar. */
declare(strict_types=1);

if (! defined('ABSPATH')) exit;

$items = function_exists('intercargo_world_clock_items') ? intercargo_world_clock_items() : [];
if ($items === []) return;

$clock_icon = get_theme_file_uri('design/components/announcement/clock.svg');
?>
<div class="announce" aria-label="<?php esc_attr_e('Local times', 'intercargo-vite'); ?>">
    <div class="announce-inner">
    <p class="announce-label"><?php echo esc_html((string) apply_filters('intercargo_world_clock_heading', __('Current time in:', 'intercargo-vite'))); ?></p>
    <?php foreach ($items as $item) :
        $label = (string) ($item['label'] ?? '');
        $timezone = (string) ($item['timezone'] ?? '');
        if ($label === '' || $timezone === '') continue;
        $time = function_exists('intercargo_world_clock_time') ? intercargo_world_clock_time($timezone) : '';
    ?>
        <div class="announce-item">
            <img src="<?php echo esc_url($clock_icon); ?>" alt="" aria-hidden="true">
            <span class="announce-time">
                <span class="announce-city"><?php echo esc_html($label); ?></span>
                <time class="announce-clock" data-tz="<?php echo esc_attr($timezone); ?>"><?php echo esc_html($time); ?></time>
            </span>
        </div>
    <?php endforeach; ?>
    </div>
</div>
