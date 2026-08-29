<?php
/** Shared section UX backed by WordPress Core synced-pattern (`wp_block`) records. */
declare(strict_types=1);

if (! defined('ABSPATH')) exit;

const INTERCARGO_SHARED_SECTION_TYPE_META = '_intercargo_section_type';
const INTERCARGO_SHARED_SECTION_KEY_META  = '_intercargo_shared_key';

/** @return array<string,string> slug => canonical block name */
function intercargo_syncable_section_types(): array {
    $types = [];
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir);
        if (! is_array($meta)) continue;
        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        if (($cfg['sectionPackage'] ?? false) !== true || ($cfg['syncable'] ?? true) === false) continue;
        $name = (string) ($meta['name'] ?? '');
        if (preg_match('#^intercargo/([a-z][a-z0-9-]*)$#', $name, $m) !== 1) continue;
        $types[$m[1]] = $name;
    }
    ksort($types, SORT_STRING);
    return $types;
}

/** Infer the single Intercargo top-level section stored inside a synced pattern. */
function intercargo_shared_section_type_from_content(string $content): ?string {
    if ($content === '' || ! function_exists('parse_blocks')) return null;
    $blocks = parse_blocks($content);
    $named = array_values(array_filter($blocks, static fn($block): bool => is_array($block) && ! empty($block['blockName'])));
    if (count($named) !== 1) return null;
    $name = (string) ($named[0]['blockName'] ?? '');
    $types = intercargo_syncable_section_types();
    foreach ($types as $slug => $canonical) {
        if ($name === $canonical) return $slug;
    }
    return null;
}


/** Return true when parsed post content references a synced pattern ID. */
function intercargo_content_references_shared_section(string $content, int $shared_id): bool {
    if ($content === '' || $shared_id <= 0 || ! function_exists('parse_blocks')) return false;

    $walk = static function (array $blocks) use (&$walk, $shared_id): bool {
        foreach ($blocks as $block) {
            if (! is_array($block)) continue;
            if (($block['blockName'] ?? '') === 'core/block' && (int) ($block['attrs']['ref'] ?? 0) === $shared_id) {
                return true;
            }
            $inner = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
            if ($inner && $walk($inner)) return true;
        }
        return false;
    };

    return $walk(parse_blocks($content));
}

/**
 * List editable public content entries that reference a shared section.
 *
 * @return array<int,array<string,mixed>>
 */
function intercargo_shared_section_usage(int $shared_id): array {
    if ($shared_id <= 0) return [];

    $post_types = get_post_types(['public' => true], 'names');
    if (! is_array($post_types)) $post_types = [];
    unset($post_types['attachment']);
    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));
    if (! $post_types) return [];

    global $wpdb;
    if (! isset($wpdb->posts)) return [];

    $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $compact = '%"ref":' . $shared_id . '%';
    $spaced  = '%"ref": ' . $shared_id . '%';
    $sql = "SELECT ID FROM {$wpdb->posts}
            WHERE post_type IN ({$type_placeholders})
              AND post_status NOT IN ('trash','auto-draft','inherit')
              AND (post_content LIKE %s OR post_content LIKE %s)
            ORDER BY post_title ASC, ID ASC";
    $params = array_merge($post_types, [$compact, $spaced]);
    $candidate_ids = $wpdb->get_col($wpdb->prepare($sql, ...$params));
    if (! is_array($candidate_ids)) return [];

    $usage = [];
    foreach (array_map('absint', $candidate_ids) as $post_id) {
        if ($post_id <= 0 || ! current_user_can('edit_post', $post_id)) continue;
        $post = get_post($post_id);
        if (! $post instanceof WP_Post) continue;
        if (! intercargo_content_references_shared_section((string) $post->post_content, $shared_id)) continue;

        $type_obj = get_post_type_object((string) $post->post_type);
        $usage[] = [
            'id'            => $post_id,
            'title'         => $post->post_title !== '' ? $post->post_title : __('(no title)', 'intercargo-vite'),
            'postType'      => (string) $post->post_type,
            'postTypeLabel' => $type_obj && isset($type_obj->labels->singular_name) ? (string) $type_obj->labels->singular_name : (string) $post->post_type,
            'status'        => (string) $post->post_status,
            'editUrl'       => (string) (get_edit_post_link($post_id, 'raw') ?: ''),
            'viewUrl'       => (string) (get_permalink($post_id) ?: ''),
        ];
    }

    return $usage;
}

function intercargo_shared_section_valid_type(string $section_type): bool {
    return isset(intercargo_syncable_section_types()[$section_type]);
}

function intercargo_shared_section_permission(): bool {
    return current_user_can('edit_posts');
}

function intercargo_shared_section_post_is_readable(int $post_id): bool {
    $post = get_post($post_id);
    return $post instanceof WP_Post && $post->post_type === 'wp_block' && current_user_can('edit_post', $post_id);
}

function intercargo_shared_section_ensure_key(int $post_id): string {
    $key = (string) get_post_meta($post_id, INTERCARGO_SHARED_SECTION_KEY_META, true);
    if ($key !== '') return $key;
    $key = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('shared-', true);
    update_post_meta($post_id, INTERCARGO_SHARED_SECTION_KEY_META, $key);
    return $key;
}

/** @return array<string,mixed>|null */
function intercargo_shared_section_record(WP_Post $post, bool $include_content = false): ?array {
    if ($post->post_type !== 'wp_block') return null;
    // Only fully synced Core patterns belong in this workflow. Unsynced/partial
    // patterns have different instance semantics and must not be silently adopted.
    $sync_status = (string) get_post_meta($post->ID, 'wp_pattern_sync_status', true);
    if (in_array($sync_status, ['unsynced', 'partial'], true)) return null;
    $type = (string) get_post_meta($post->ID, INTERCARGO_SHARED_SECTION_TYPE_META, true);
    if (! intercargo_shared_section_valid_type($type)) {
        $type = intercargo_shared_section_type_from_content((string) $post->post_content) ?? '';
        if ($type === '') return null;
        if (current_user_can('edit_post', $post->ID)) update_post_meta($post->ID, INTERCARGO_SHARED_SECTION_TYPE_META, $type);
    }

    $record = [
        'id'          => (int) $post->ID,
        'title'       => $post->post_title !== '' ? $post->post_title : __('Untitled shared section', 'intercargo-vite'),
        'sectionType' => $type,
        'sharedKey'   => current_user_can('edit_post', $post->ID) ? intercargo_shared_section_ensure_key((int) $post->ID) : '',
        'modified'    => mysql_to_rfc3339((string) $post->post_modified_gmt),
    ];
    if ($include_content) {
        $record['content'] = (string) $post->post_content;
        $record['usage'] = intercargo_shared_section_usage((int) $post->ID);
        $record['usageCount'] = count($record['usage']);
    }
    return $record;
}

/** @return WP_Post[] */
function intercargo_shared_section_posts(): array {
    $posts = get_posts([
        'post_type'              => 'wp_block',
        'post_status'            => ['publish', 'private', 'draft'],
        'posts_per_page'         => 250,
        'orderby'                => 'title',
        'order'                  => 'ASC',
        'suppress_filters'       => false,
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
    ]);
    return is_array($posts) ? array_values(array_filter($posts, static fn($post): bool => $post instanceof WP_Post)) : [];
}

function intercargo_rest_list_shared_sections(WP_REST_Request $request): WP_REST_Response {
    $wanted = sanitize_key((string) $request->get_param('sectionType'));
    $records = [];
    foreach (intercargo_shared_section_posts() as $post) {
        if (! current_user_can('edit_post', $post->ID)) continue;
        $record = intercargo_shared_section_record($post, false);
        if ($record === null || ($wanted !== '' && $record['sectionType'] !== $wanted)) continue;
        $records[] = $record;
    }
    usort($records, static fn(array $a, array $b): int => strnatcasecmp((string) $a['title'], (string) $b['title']));
    return rest_ensure_response(['items' => $records]);
}

function intercargo_rest_get_shared_section(WP_REST_Request $request): WP_REST_Response|WP_Error {
    $post_id = absint($request['id']);
    if (! intercargo_shared_section_post_is_readable($post_id)) {
        return new WP_Error('intercargo_shared_not_found', __('Shared section not found.', 'intercargo-vite'), ['status' => 404]);
    }
    $record = intercargo_shared_section_record(get_post($post_id), true);
    return $record ? rest_ensure_response($record) : new WP_Error('intercargo_shared_invalid', __('This synced pattern is not an Intercargo section.', 'intercargo-vite'), ['status' => 404]);
}

function intercargo_rest_create_shared_section(WP_REST_Request $request): WP_REST_Response|WP_Error {
    $section_type = sanitize_key((string) $request->get_param('sectionType'));
    $title = sanitize_text_field((string) $request->get_param('title'));
    $content = (string) $request->get_param('content');

    if (! intercargo_shared_section_valid_type($section_type)) {
        return new WP_Error('intercargo_shared_type', __('Invalid section type.', 'intercargo-vite'), ['status' => 400]);
    }
    if ($title === '') {
        return new WP_Error('intercargo_shared_title', __('Give this shared section a name.', 'intercargo-vite'), ['status' => 400]);
    }
    if (intercargo_shared_section_type_from_content($content) !== $section_type) {
        return new WP_Error('intercargo_shared_content', __('Shared content must contain exactly one matching Intercargo section.', 'intercargo-vite'), ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_type'    => 'wp_block',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $content,
        'post_author'  => get_current_user_id(),
    ], true);
    if (is_wp_error($post_id)) return $post_id;

    update_post_meta((int) $post_id, INTERCARGO_SHARED_SECTION_TYPE_META, $section_type);
    intercargo_shared_section_ensure_key((int) $post_id);
    $created_post = get_post((int) $post_id);
    $record = $created_post instanceof WP_Post ? intercargo_shared_section_record($created_post, true) : null;
    return rest_ensure_response($record ?? []);
}

function intercargo_register_shared_section_rest_routes(): void {
    register_rest_route('intercargo/v1', '/shared-sections', [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'intercargo_rest_list_shared_sections',
            'permission_callback' => 'intercargo_shared_section_permission',
            'args'                => [
                'sectionType' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
            ],
        ],
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'intercargo_rest_create_shared_section',
            'permission_callback' => 'intercargo_shared_section_permission',
        ],
    ]);
    register_rest_route('intercargo/v1', '/shared-sections/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'intercargo_rest_get_shared_section',
        'permission_callback' => 'intercargo_shared_section_permission',
        'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
    ]);
}
add_action('rest_api_init', 'intercargo_register_shared_section_rest_routes');

function intercargo_register_shared_section_meta(): void {
    if (! function_exists('register_post_meta')) return;
    register_post_meta('wp_block', INTERCARGO_SHARED_SECTION_TYPE_META, [
        'type' => 'string', 'single' => true, 'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_key',
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
    register_post_meta('wp_block', INTERCARGO_SHARED_SECTION_KEY_META, [
        'type' => 'string', 'single' => true, 'show_in_rest' => false,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
}
add_action('init', 'intercargo_register_shared_section_meta', 21);

function intercargo_enqueue_shared_section_editor(): void {
    $path = get_theme_file_path('inc/shared-sections-editor.js');
    if (! is_file($path)) return;
    $handle = 'intercargo-shared-sections-editor';
    wp_register_script(
        $handle,
        get_theme_file_uri('inc/shared-sections-editor.js'),
        ['wp-api-fetch', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n'],
        substr(hash_file('sha256', $path) ?: '1', 0, 16),
        true
    );
    wp_add_inline_script($handle, 'window.intercargoSharedSectionsConfig = ' . wp_json_encode([
        'restPath' => '/intercargo/v1/shared-sections',
        'sectionNames' => array_values(intercargo_syncable_section_types()),
    ]) . ';', 'before');
    wp_enqueue_script($handle);
}
add_action('enqueue_block_editor_assets', 'intercargo_enqueue_shared_section_editor', 45);
