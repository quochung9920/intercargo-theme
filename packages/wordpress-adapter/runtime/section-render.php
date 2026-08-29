<?php
/**
 * Shared renderer for native sections.
 *
 * Every native section is the same shape: a wrapper the design owns, and stored block
 * content the client owns. The wrapper's classes and label come from the section's
 * template.json, so a section is defined in exactly one place.
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render a native section, or nothing at all.
 *
 * Fails closed: a stored tree that does not match the declared composition, or that
 * carries a rejected URL, produces no output rather than a partly valid section.
 */
function intercargo_render_native_section(string $slug, string $content, $block): void
{
    $inner = ($block instanceof WP_Block && isset($block->parsed_block['innerBlocks']))
        ? (array) $block->parsed_block['innerBlocks']
        : [];

    if ($inner === []) {
        return;
    }

    if (! intercargo_section_composition_is_valid($slug, $inner)) {
        intercargo_log_composition_failure($slug, $inner);
        return;
    }

    $config = intercargo_composition_section_config($slug);
    if ($config === null) {
        return;
    }

    // Preserve the historical anchor by default, but allow a page-local fixed
    // sectionAnchor when an editor needs a stable Service Navigation target. The
    // same intercargo_section_id() uniquifier still protects repeated/fixed IDs.
    $base_id = (string) ($config['id'] ?? '');
    if ($block instanceof WP_Block) {
        $requested = trim((string) ($block->attributes['sectionAnchor'] ?? ''));
        if ($requested !== '') {
            $requested = ltrim($requested, '#');
            $base_id = sanitize_html_class($requested, $base_id);
        }
    }
    $runtime_id = $base_id === '' ? '' : intercargo_section_id($base_id);
    $id_attribute = $runtime_id === '' ? '' : sprintf(' id="%s"', esc_attr($runtime_id));
    printf(
        '<section%1$s class="%2$s" aria-label="%3$s">%4$s</section>',
        $id_attribute, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
        esc_attr($config['className']),
        esc_attr($config['ariaLabel']),
        $content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stored block content, validated above.
    );
}

/**
 * Render one named child block — a repeating item inside a section's open collection.
 *
 * A pure wrapper by design. Validation belongs to the parent section, whose gate walks
 * the whole stored tree including these rows; a rejected tree renders nothing, so an
 * item can never reach the page outside a section that has already passed.
 */
function intercargo_render_native_item(string $slug, string $content): void
{
    $config = intercargo_composition_item_config($slug);
    if ($config === null || trim($content) === '') {
        return;
    }

    printf(
        '<%1$s class="%2$s">%3$s</%1$s>',
        $config['element'],
        esc_attr($config['className']),
        $content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stored block content, validated by the parent section.
    );
}

/**
 * Remove legacy descendant anchors before a link-owning item wraps its content.
 * New Service Card templates contain no anchors; this compatibility guard prevents
 * invalid nested links if an older saved CTA reaches the item during migration.
 */
function intercargo_strip_nested_anchor(string $content): string
{
    $without_openers = preg_replace('#<a\b[^>]*>#i', '<span>', $content);
    if (! is_string($without_openers)) {
        return '';
    }
    $without_closers = preg_replace('#</a>#i', '</span>', $without_openers);
    return is_string($without_closers) ? $without_closers : '';
}

/**
 * Render a named item whose URL belongs to the wrapper, never to inner text blocks.
 */
function intercargo_render_linked_native_item(string $slug, array $attributes, string $content): void
{
    $config = intercargo_composition_item_config($slug);
    $url = trim((string) ($attributes['url'] ?? ''));
    $target = (string) ($attributes['target'] ?? '_self');
    if (
        $config === null
        || empty($config['linkOwner'])
        || trim($content) === ''
        || ! intercargo_is_allowed_url($url)
        || ! in_array($target, intercargo_allowed_link_targets(), true)
    ) {
        return;
    }

    $rel = intercargo_link_rel_for_target($target);
    $rel_attribute = $rel === '' ? '' : sprintf(' rel="%s"', esc_attr($rel));
    printf(
        '<a class="%1$s" href="%2$s" target="%3$s"%4$s>%5$s</a>',
        esc_attr($config['className']),
        esc_url($url),
        esc_attr($target),
        $rel_attribute, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
        intercargo_strip_nested_anchor($content) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated by parent composition.
    );
}
