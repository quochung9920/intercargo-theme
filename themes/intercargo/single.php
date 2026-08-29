<?php
declare(strict_types=1);
get_header();
?>
<main class="page" id="main-content"><div class="container section-pad">
<?php while (have_posts()) : the_post(); ?><article <?php post_class(); ?>><h1 class="section-title"><?php the_title(); ?></h1><?php the_content(); ?></article><?php endwhile; ?>
</div></main>
<?php get_footer();
