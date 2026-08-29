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
<main class="page" id="main-content">
<?php echo $rendered_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical the_content output. ?>
</main>
<?php get_footer();
