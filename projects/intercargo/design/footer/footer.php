<?php
/** Footer */
declare(strict_types=1);
$columns = [
    'footer_services' => __('Services', 'intercargo-vite'),
    'footer_locations' => __('Locations', 'intercargo-vite'),
    'footer_company' => __('Company', 'intercargo-vite'),
];
?>
<footer class="footer" id="footer">
    <img class="footer-dots" src="<?php echo esc_url(intercargo_asset_url('0bbae2eff92b0fbfb9ad1f03ac91b2911ad978db.png')); ?>" alt="" aria-hidden="true">
    <div class="footer-inner"><div class="footer-top"><div class="footer-brand"><a class="footer-logo" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(intercargo_brand_logo_url()); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></a><p class="footer-intro"><?php echo esc_html(intercargo_brand_text('footer_intro')); ?></p></div><div class="footer-cols">
    <?php foreach ($columns as $location => $title) : ?><?php if (has_nav_menu($location)) : ?><nav class="footer-col" aria-label="<?php echo esc_attr($title); ?>"><p class="footer-col-title"><?php echo esc_html($title); ?></p><?php wp_nav_menu(['theme_location' => $location, 'container' => false, 'menu_class' => 'unstyled-list', 'depth' => 2, 'fallback_cb' => false]); ?></nav><?php endif; ?><?php endforeach; ?>
    </div></div><?php $legal_line = intercargo_brand_text('legal_line'); $privacy_url = get_privacy_policy_url(); ?><div class="footer-legal"><span>© <?php echo esc_html(get_bloginfo('name')); ?><?php if ($legal_line !== '') : ?>. <?php echo esc_html($legal_line); ?><?php endif; ?></span><?php if ($privacy_url !== '') : ?><a class="footer-privacy" href="<?php echo esc_url($privacy_url); ?>">Privacy</a><?php else : ?><span class="footer-privacy" aria-disabled="true">Privacy</span><?php endif; ?></div></div>
</footer>
<?php wp_footer(); ?>
</body></html>
