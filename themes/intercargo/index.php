<?php
declare(strict_types=1);
get_header();
?>
<main class="page" id="main-content"><div class="container section-pad">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?><article <?php post_class(); ?>><h1 class="section-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1><?php the_content(); ?></article><?php endwhile; else : ?><p><?php esc_html_e('No content found.', 'intercargo-vite'); ?></p><?php endif; ?>
</div></main>
<?php get_footer();
