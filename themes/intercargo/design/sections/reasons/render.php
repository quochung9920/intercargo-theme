<?php
/**
 * Content Columns renderer.
 *
 * 4.11.2+ stores columns as native child blocks. A compact legacy fallback keeps
 * pre-4.11.2 attribute-based Reasons content visible until the page is opened and
 * saved, at which point editor.js hydrates it into Content Column items.
 */
declare(strict_types=1);
if (! defined('ABSPATH')) exit;

$theme = isset($attributes['theme']) && is_scalar($attributes['theme'])
    ? sanitize_key((string) $attributes['theme'])
    : 'homepage';
if (! in_array($theme, ['homepage', 'service-paper', 'service-white', 'about-paper'], true)) {
    $theme = 'homepage';
}

$inner = ($block instanceof WP_Block && isset($block->parsed_block['innerBlocks']))
    ? (array) $block->parsed_block['innerBlocks']
    : [];

if ($inner !== []) {
    if (! intercargo_section_composition_is_valid('reasons', $inner)) {
        intercargo_log_composition_failure('reasons', $inner);
        return;
    }
    $config = intercargo_composition_section_config('reasons');
    if ($config === null) return;

    $base_id = (string) ($config['id'] ?? 'about');
    $requested = trim((string) ($attributes['sectionAnchor'] ?? ''));
    if ($requested !== '') {
        $requested = ltrim($requested, '#');
        $base_id = sanitize_html_class($requested, $base_id);
    }
    $runtime_id = $base_id === '' ? '' : intercargo_section_id($base_id);
    $id_attribute = $runtime_id === '' ? '' : sprintf(' id="%s"', esc_attr($runtime_id));
    $classes = trim((string) $config['className'] . ' content-columns--' . $theme);
    printf(
        '<section%1$s class="%2$s" aria-label="%3$s">%4$s</section>',
        $id_attribute, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
        esc_attr($classes),
        esc_attr((string) ($config['ariaLabel'] ?? 'Content columns')),
        $content ?? '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated stored blocks.
    );
    return;
}

// Legacy attribute-based Reasons fallback. This branch disappears naturally per
// page after the editor hydrates and saves the native child-block composition.
$heading = intercargo_native_or_legacy_text($attributes, 'title', 'title', 'Why importers switch.');
$intro = intercargo_native_or_legacy_text($attributes, 'intro', 'intro', '');
$items = intercargo_native_or_legacy_rows(
    $attributes,
    'reasons',
    'reasons',
    ['title', 'copy'],
    [
        ['title' => 'The bill matches the quote.', 'copy' => 'This industry\'s worst habit is the fee stack: cartage fee, dehire fee, invoice after invoice. We price all-in and put it in writing before your freight moves.'],
        ['title' => 'You never chase us.', 'copy' => 'You hear from us before you have to ask. Milestone updates on every shipment, and a person who picks up the phone.'],
        ['title' => 'One team owns it.', 'copy' => 'Forwarding, customs, cartage and warehousing under one roof. Nothing gets handballed to a third party you have never met.'],
    ]
);
[$section_id, $section_attrs] = intercargo_native_section_attrs($attributes, 'about', 'reasons');
$title_id = $section_id . '-title';
?>
<section class="wp-block-acf-intercargo-reasons why-section content-columns section-pad content-columns--<?php echo esc_attr($theme); ?>" <?php echo $section_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="<?php echo esc_attr($title_id); ?>">
    <div class="container content-columns-content content-columns-layout">
        <h2 class="section-title" id="<?php echo esc_attr($title_id); ?>"><?php echo esc_html($heading); ?></h2>
        <?php if ($intro !== '') : ?><p class="content-columns-intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
        <div class="three-cols content-columns-grid">
            <?php foreach ($items as $item) : ?>
                <article class="reason-card content-column">
                    <h3 class="content-column-title"><?php echo esc_html((string) ($item['title'] ?? '')); ?></h3>
                    <p class="content-column-copy"><?php echo esc_html((string) ($item['copy'] ?? '')); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
