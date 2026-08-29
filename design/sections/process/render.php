<?php
declare(strict_types=1);
if (! defined('ABSPATH')) exit;

$heading = intercargo_native_or_legacy_text($attributes, 'heading', 'heading', 'How it starts.');
$items = intercargo_native_or_legacy_rows(
    $attributes,
    'steps',
    'steps',
    ['title', 'copy'],
    [['title' => 'Talk to a person', 'copy' => 'Tell us what you are importing and how often. A senior team member replies the same business day.'],
    ['title' => 'Get your plan and price', 'copy' => 'One written quote covering every leg and every fee. No surprises later, that is the point.'],
    ['title' => 'Your freight moves', 'copy' => 'We book, clear and deliver, and you hear from us at every milestone without asking.']]
);
[$section_id, $section_attrs] = intercargo_native_section_attrs($attributes, 'process', 'process');
$title_id = $section_id . '-title';
?>
<section class="wp-block-acf-intercargo-process how-section section-pad" <?php echo $section_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="<?php echo esc_attr($title_id); ?>"><div class="container section-head-gap"><h2 class="section-title" id="<?php echo esc_attr($title_id); ?>"><?php echo esc_html($heading); ?></h2><div class="three-cols"><?php foreach ($items as $item) : ?><article class="reason-card"><h3><?php echo esc_html((string) ($item['title'] ?? '')); ?></h3><p><?php echo esc_html((string) ($item['copy'] ?? '')); ?></p></article><?php endforeach; ?></div></div></section>
