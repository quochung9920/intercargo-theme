<?php
declare(strict_types=1);

$rendered_content = '';
if (have_posts()) {
    the_post();
    // Resolve blocks before wp_head so WordPress can print their hashed styles in HEAD.
    $rendered_content = (string) apply_filters('the_content', get_the_content());
}

get_header();
?>
<main class="page" id="main-content"><div class="container section-pad">
<?php if ($rendered_content !== '') : ?><article <?php post_class(); ?>><h1 class="section-title"><?php the_title(); ?></h1><?php echo $rendered_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical the_content output. ?></article><?php endif; ?>
</div></main>
<?php get_footer();
