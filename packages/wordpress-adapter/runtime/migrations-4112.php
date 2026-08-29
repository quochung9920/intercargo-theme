<?php
/** One-time cleanup migration for the removed Service Criteria block family. */
declare(strict_types=1);
if (! defined('ABSPATH')) exit;

/** Convert serialized 4.11.0 Service Criteria markup into canonical Content Columns. */
function intercargo_migrate_service_criteria_markup_4112(string $content): string
{
    if (! str_contains($content, 'wp:intercargo/service-criteria') && ! str_contains($content, 'wp:intercargo/service-criterion')) {
        return $content;
    }

    $content = preg_replace_callback(
        '#<!--\s+wp:intercargo/service-criteria(?P<attrs>\s+\{.*?\})?\s*(?P<self>/)?-->#s',
        static function (array $matches): string {
            $attrs = [];
            $raw = trim((string) ($matches['attrs'] ?? ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $attrs = $decoded;
            }
            // The removed block represented the paper service-page preset.
            $attrs['theme'] = 'service-paper';
            $json = wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $json = is_string($json) && $json !== '[]' ? ' ' . $json : '';
            $self = (($matches['self'] ?? '') === '/') ? ' /-->' : ' -->';
            return '<!-- wp:intercargo/reasons' . $json . $self;
        },
        $content
    ) ?? $content;

    $content = str_replace(
        ['<!-- /wp:intercargo/service-criteria -->', 'wp:intercargo/service-criterion'],
        ['<!-- /wp:intercargo/reasons -->', 'wp:intercargo/content-column'],
        $content
    );

    // Align the removed block's stored native composition with the one canonical
    // Content Columns composition. These are serialized block classes, not visual
    // guesses; the replacement lets the normal composition gate validate it.
    return str_replace(
        [
            'service-criteria-content',
            'service-criteria-heading',
            'service-criteria-intro',
            'service-criteria-grid',
            'service-criterion-title',
            'service-criterion-body',
        ],
        [
            'content-columns-content content-columns-layout',
            'section-title',
            'content-columns-intro',
            'three-cols content-columns-grid',
            'content-column-title',
            'content-column-copy',
        ],
        $content
    );
}

/**
 * Remove the obsolete block family from stored page/shared-pattern content once.
 * No compatibility block is registered: after this migration the old identities
 * are absent from both the filesystem and normal content.
 */
function intercargo_migrate_service_criteria_posts_4112(): void
{
    if (get_option('intercargo_service_criteria_removed_4112')) return;

    global $wpdb;
    if (! isset($wpdb->posts)) return;

    $like_section = '%' . $wpdb->esc_like('wp:intercargo/service-criteria') . '%';
    $like_item = '%' . $wpdb->esc_like('wp:intercargo/service-criterion') . '%';
    $sql = $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item') AND (post_content LIKE %s OR post_content LIKE %s)",
        $like_section,
        $like_item
    );
    $ids = $wpdb->get_col($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared immediately above.
    $failed = false;

    foreach ((array) $ids as $raw_id) {
        $post_id = (int) $raw_id;
        if ($post_id <= 0) continue;
        $post = get_post($post_id);
        if (! $post instanceof WP_Post) continue;
        $before = (string) $post->post_content;
        $after = intercargo_migrate_service_criteria_markup_4112($before);
        if ($after === $before) continue;
        $result = wp_update_post(['ID' => $post_id, 'post_content' => $after], true);
        if (is_wp_error($result)) $failed = true;
    }

    if (! $failed) update_option('intercargo_service_criteria_removed_4112', 1, false);
}
add_action('init', 'intercargo_migrate_service_criteria_posts_4112', 45);
