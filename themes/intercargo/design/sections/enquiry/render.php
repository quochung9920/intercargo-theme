<?php
declare(strict_types=1); if (! defined('ABSPATH')) exit;
$title = intercargo_native_or_legacy_text($attributes,'title','title','Talk to the team.');
$lead = intercargo_native_or_legacy_text($attributes,'lead','lead','Tell us what you are importing and how often. A senior person replies the same business day, with a real answer, not a ticket number.');
$step_fallback = [
 ['title'=>'We reply the same business day','copy'=>'A senior person, across your enquiry.'],
 ['title'=>'We scope it in one conversation','copy'=>'Route, volume, timing and what you have been paying, if you want it beaten.'],
 ['title'=>'You get one written price','copy'=>'Every fee included. Take it or keep it as a benchmark, no pressure either way.'],
];
if (array_key_exists('steps', $attributes) && is_array($attributes['steps'])) {
    // Native editor state is authoritative, including an intentionally empty list.
    $steps = [];
    foreach ($attributes['steps'] as $step) {
        if (! is_array($step)) continue;
        $steps[] = [
            'title' => trim(wp_strip_all_tags((string) ($step['title'] ?? ''))),
            'copy'  => trim(wp_strip_all_tags((string) ($step['copy'] ?? ''))),
        ];
    }
} else {
    $steps = intercargo_native_or_legacy_rows($attributes,'steps','steps',['title','copy'],$step_fallback);
}
$contact_note = intercargo_native_or_legacy_text($attributes,'contactNote','contact_note','');
if (trim($contact_note) === 'Phone and direct email publish at launch.') $contact_note = '';
$form_label = intercargo_native_or_legacy_text($attributes,'formLabel','form_label','Enquiry form');
$form_note = intercargo_native_or_legacy_text($attributes,'formNote','form_note','A person replies. No quote-bot, no ticket queue.');
$legacy_shortcode = intercargo_native_or_legacy_text($attributes,'legacyShortcode','form_shortcode','');
$provider = sanitize_key((string)($attributes['provider'] ?? '')); $form_id = trim((string)($attributes['formId'] ?? ''));
[$section_id,$section_attrs] = intercargo_native_section_attrs($attributes,'enquiry','enquiry'); $title_id=$section_id.'-title';
$form_markup = intercargo_render_form_adapter(['provider'=>$provider,'formId'=>$form_id,'legacyShortcode'=>$legacy_shortcode,'variant'=>'enquiry','label'=>$form_label,'note'=>$form_note]);
?>
<section class="wp-block-acf-intercargo-enquiry enquiry-section section-pad" <?php echo $section_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="<?php echo esc_attr($title_id); ?>"><div class="container enquiry-layout"><div class="enquiry-copy"><h2 class="section-title" id="<?php echo esc_attr($title_id); ?>"><?php echo esc_html($title); ?></h2><p class="enquiry-lead"><?php echo esc_html($lead); ?></p><div class="enquiry-steps"><?php foreach($steps as $step): ?><article class="enquiry-step"><h3><?php echo esc_html((string)($step['title']??'')); ?></h3><p><?php echo esc_html((string)($step['copy']??'')); ?></p></article><?php endforeach; ?></div><p class="enquiry-note enquiry-note--desktop"><?php echo esc_html($contact_note); ?></p></div><?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p class="enquiry-note enquiry-note--mobile"><?php echo esc_html($contact_note); ?></p></div></section>
