<?php
/** Service Navigation — tabs are derived from the page's current section tree. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$attrs = is_array($attributes ?? null) ? $attributes : [];
$post_id = 0;
if ($block instanceof WP_Block) {
    $post_id = (int) ($block->context['postId'] ?? 0);
}
if ($post_id <= 0 && function_exists('get_the_ID')) {
    $post_id = (int) get_the_ID();
}
$tab_order = is_array($attrs['tabOrder'] ?? null) ? $attrs['tabOrder'] : [];
$items = function_exists('intercargo_service_navigation_items_for_post')
    ? intercargo_service_navigation_items_for_post($post_id, $tab_order)
    : [];

$primary_label = trim(wp_strip_all_tags((string) ($attrs['primaryCtaLabel'] ?? 'Talk to our team')));
$primary_url = trim((string) ($attrs['primaryCtaUrl'] ?? '#enquiry'));
$secondary_label = trim(wp_strip_all_tags((string) ($attrs['secondaryCtaLabel'] ?? 'Get the guide')));
$secondary_url = trim((string) ($attrs['secondaryCtaUrl'] ?? '#guide'));
$aria_label = trim(wp_strip_all_tags((string) ($attrs['ariaLabel'] ?? 'Service page navigation')));
if ($aria_label === '') {
    $aria_label = 'Service page navigation';
}

$primary_ok = $primary_label !== '' && function_exists('intercargo_is_allowed_url') && intercargo_is_allowed_url($primary_url);
$secondary_ok = $secondary_label !== '' && function_exists('intercargo_is_allowed_url') && intercargo_is_allowed_url($secondary_url);

$section_id = function_exists('intercargo_section_id') ? intercargo_section_id('service-navigation') : 'service-navigation';
?>
<section id="<?php echo esc_attr($section_id); ?>" class="wp-block-intercargo-service-navigation service-secnav intercargo-native intercargo-native-service-navigation" aria-label="<?php echo esc_attr($aria_label); ?>">
    <div class="service-secnav-inner container">
        <nav class="service-secnav-links" aria-label="<?php echo esc_attr__('On this page', 'intercargo-vite'); ?>">
            <?php foreach ($items as $item) : ?>
                <a class="service-secnav-link" href="#<?php echo esc_attr((string) $item['anchor']); ?>"><?php echo esc_html((string) $item['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($primary_ok || $secondary_ok) : ?>
            <div class="service-secnav-actions">
                <?php if ($primary_ok) : ?>
                    <p class="service-secnav-action service-secnav-action--primary"><a href="<?php echo esc_url($primary_url); ?>"><?php echo esc_html($primary_label); ?></a></p>
                <?php endif; ?>
                <?php if ($secondary_ok) : ?>
                    <p class="service-secnav-action service-secnav-action--secondary"><a href="<?php echo esc_url($secondary_url); ?>"><?php echo esc_html($secondary_label); ?></a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
