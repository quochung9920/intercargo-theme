<?php
/** Header */
declare(strict_types=1);
$has_primary_menu = has_nav_menu('primary');
$transparent_header = function_exists('intercargo_page_uses_transparent_header') && intercargo_page_uses_transparent_header();
$header_variant = $transparent_header ? 'transparent' : 'light';
$header_logo_url = $transparent_header
    ? intercargo_brand_logo_url()
    : (function_exists('intercargo_brand_primary_logo_url') ? intercargo_brand_primary_logo_url() : intercargo_brand_logo_url());
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-only" href="#main-content"><?php esc_html_e('Skip to content', 'intercargo-vite'); ?></a>
<?php if (function_exists('intercargo_render_announcement')) intercargo_render_announcement(); ?>
<header class="site-header site-header--<?php echo esc_attr($header_variant); ?>" id="site-header">
    <nav class="site-nav site-nav--<?php echo esc_attr($header_variant); ?>" aria-label="<?php esc_attr_e('Primary navigation', 'intercargo-vite'); ?>">
        <a class="logo site-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <img src="<?php echo esc_url($header_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
        </a>
        <?php if ($has_primary_menu) : ?>
            <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'nav-links', 'depth' => 3, 'fallback_cb' => false]); ?>
        <?php endif; ?>
        <a class="button button--yellow nav-cta" href="<?php echo esc_url(intercargo_brand_cta_url()); ?>"><?php echo esc_html(intercargo_brand_text('cta_label')); ?></a>
        <?php if ($has_primary_menu) : ?>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu">MENU</button>
        <?php endif; ?>
    </nav>
    <?php if ($has_primary_menu) : ?>
        <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
            <div class="mobile-menu-topbar"><a class="mobile-menu-logo" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url($header_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></a><button class="mobile-menu-close" type="button" aria-label="<?php esc_attr_e('Close menu', 'intercargo-vite'); ?>">CLOSE</button></div>
            <div class="mobile-menu-scroll">
                <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'mobile-menu-links', 'depth' => 3, 'fallback_cb' => false]); ?>
            </div>
            <div class="mobile-menu-cta"><span><?php echo esc_html(intercargo_brand_text('cta_label')); ?></span><a class="button button--yellow" href="<?php echo esc_url(intercargo_brand_cta_url()); ?>"><?php echo esc_html(intercargo_brand_text('cta_secondary_label')); ?></a></div>
        </div>
    <?php endif; ?>
</header>
