<?php
/**
 * Stored-composition gate.
 *
 * `templateLock` and block `lock` attributes are editor affordances. They are not
 * a runtime boundary: the code editor, a paste, the REST API or a database write
 * can all store a tree the editor would never have produced.
 *
 * This module validates a parsed block tree against a declared schema before the
 * frontend renders it, and fails closed. A partly valid tree is never rendered.
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Blocks that may never appear anywhere inside an approved section.
 */
function intercargo_composition_forbidden_blocks(): array
{
    return ['core/html', 'core/shortcode', 'core/freeform', 'core/missing', 'core/code'];
}

/**
 * Sections that have a native composition, and therefore a gate.
 *
 * Composition-backed sections are discovered from their self-contained packages.
 * There is no fallback registry or legacy `/blocks` directory in V4.
 */
function intercargo_native_section_slugs(): array
{
    if (function_exists('intercargo_package_composition_slugs')) {
        $slugs = intercargo_package_composition_slugs('section');
        if ($slugs !== []) {
            return $slugs;
        }
    }
    return ['hero', 'services', 'faq', 'guide', 'locations'];
}

/**
 * Named child blocks: repeating items a section's open collection admits.
 *
 * A row with several distinct fields is too structured for core/list, and a bare
 * core/group would appear in the inserter as "Group" and guarantee nothing about which
 * fields are present or in what order. A named item block inserts a complete row.
 */
function intercargo_native_item_slugs(): array
{
    if (function_exists('intercargo_package_composition_slugs')) {
        $slugs = intercargo_package_composition_slugs('item');
        if ($slugs !== []) {
            return $slugs;
        }
    }
    return ['location', 'service-card', 'hero-email-form', 'guide-email-form'];
}

function intercargo_native_composition_slugs(): array
{
    return array_merge(intercargo_native_section_slugs(), intercargo_native_item_slugs());
}

/**
 * Read a section's declared composition.
 *
 * template.json is the single source of truth: the editor builds its block template
 * from it, this gate builds its schema from it, and the contract tests read it. One
 * file, so the editor, the renderer and the tests cannot describe three different
 * sections.
 */
function intercargo_composition_data(string $slug): ?array
{
    static $cache = [];
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }
    $cache[$slug] = null;
    if (! in_array($slug, intercargo_native_composition_slugs(), true)) {
        return null;
    }

    $path = '';
    if (function_exists('intercargo_block_package_directory_for_composition_slug')) {
        $package = intercargo_block_package_directory_for_composition_slug($slug);
        if (is_string($package) && $package !== '') {
            $path = $package . '/template.json';
        }
    }
    if (! is_readable($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (! is_array($data) || ! isset($data['template']) || ! is_array($data['template'])) {
        return null;
    }
    $cache[$slug] = $data;
    return $data;
}

function intercargo_composition_template(string $slug): ?array
{
    $data = intercargo_composition_data($slug);
    return $data === null ? null : $data['template'];
}

/**
 * The section wrapper the design owns: its classes and its accessible label.
 */
function intercargo_composition_section_config(string $slug): ?array
{
    $data = intercargo_composition_data($slug);
    if ($data === null) {
        return null;
    }
    $section = is_array($data['section'] ?? null) ? $data['section'] : [];
    $class = (string) ($section['className'] ?? '');
    if ($class === '') {
        return null;
    }
    $id = (string) ($section['id'] ?? '');
    if ($id !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $id) !== 1) {
        return null;
    }
    return [
        'className' => $class,
        'ariaLabel' => (string) ($section['ariaLabel'] ?? ''),
        'id' => $id,
    ];
}

/**
 * The wrapper element and classes for a named child block.
 */
function intercargo_composition_item_config(string $slug): ?array
{
    $data = intercargo_composition_data($slug);
    if ($data === null) {
        return null;
    }
    $item = is_array($data['item'] ?? null) ? $data['item'] : [];
    $class = (string) ($item['className'] ?? '');
    if ($class === '') {
        return null;
    }
    $element = (string) ($item['element'] ?? 'div');
    $link_owner = ($item['linkOwner'] ?? false) === true;
    // Closed list: the element name reaches the page, so it is never taken on trust.
    $allowed_elements = ['div', 'article', 'li', 'section'];
    if ($link_owner) {
        $allowed_elements[] = 'a';
    }
    if (! in_array($element, $allowed_elements, true)) {
        $element = 'div';
    }
    return ['element' => $element, 'className' => $class, 'linkOwner' => $link_owner];
}

/**
 * Convert one WordPress block-template node into a validation node.
 *
 * The template carries editor concerns too — placeholders, metadata names, lock
 * settings. The gate cares only about identity, required classes and shape.
 */
function intercargo_schema_from_template_node(array $node): array
{
    $attributes = is_array($node[1] ?? null) ? $node[1] : [];
    $children = is_array($node[2] ?? null) ? $node[2] : [];
    $classes = preg_split('#\s+#', trim((string) ($attributes['className'] ?? ''))) ?: [];

    $name = (string) ($node[0] ?? '');

    /*
     * A named child block declares its own fields in its own template.json. Resolving
     * it here keeps one description per block: the section says which item it admits,
     * the item says what it contains, and neither restates the other.
     */
    if ($children === [] && str_starts_with($name, 'intercargo/')) {
        $item = intercargo_composition_template(substr($name, strlen('intercargo/')));
        if (is_array($item)) {
            $children = $item;
        }
    }

    $schema = [
        'name' => $name,
        'classes' => array_values(array_filter($classes)),
        'children' => array_map('intercargo_schema_from_template_node', $children),
    ];

    /*
     * An open collection (ADR D10 tier 2) declares `templateLock: false`: the client is
     * meant to add and remove items. Validating its children by exact count would make
     * the gate reject the very edit the tier exists to allow, so instead every child is
     * held to the shape of the first declared item.
     */
    if (($node[1]['templateLock'] ?? null) === false && $children !== []) {
        $schema['open'] = true;
        $schema['item'] = $schema['children'][0];
        unset($schema['children']);
    }

    return $schema;
}

function intercargo_composition_schemas(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = [];
    foreach (intercargo_native_section_slugs() as $slug) {
        $template = intercargo_composition_template($slug);
        if ($template === null) {
            continue;
        }
        $cache[$slug] = array_map('intercargo_schema_from_template_node', $template);
    }
    return $cache;
}

/**
 * @return array|null A list of schema nodes, or null when the section is not gated.
 */
function intercargo_composition_schema(string $slug): ?array
{
    return intercargo_composition_schemas()[$slug] ?? null;
}

/**
 * Previous saved shapes accepted only as a rollback/migration boundary.
 * The editor never inserts these templates; they keep already-published content
 * rendering until an editor explicitly replaces it with the new semantic blocks.
 *
 * @return array<int, array>
 */
function intercargo_legacy_composition_schemas(string $slug): array
{
    static $cache = [];
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }
    $cache[$slug] = [];

    $directories = [];
    if (function_exists('intercargo_block_package_directory_for_composition_slug')) {
        $package = intercargo_block_package_directory_for_composition_slug($slug);
        if (is_string($package) && $package !== '') {
            $directories[] = $package;
        }
    }
    foreach (array_values(array_unique($directories)) as $directory) {
        foreach (glob(rtrim($directory, '/') . '/legacy-template-*.json') ?: [] as $path) {
            if (! is_readable($path)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($path), true);
            if (! is_array($data) || ! is_array($data['template'] ?? null)) {
                continue;
            }
            $cache[$slug][] = array_map('intercargo_schema_from_template_node', $data['template']);
        }
    }
    return $cache[$slug];
}

/**
 * Drop the whitespace-only entries `parse_blocks()` interleaves between blocks.
 */
function intercargo_meaningful_blocks(array $blocks): array
{
    return array_values(array_filter(
        $blocks,
        static fn($block): bool => is_array($block) && ($block['blockName'] ?? null) !== null
    ));
}

function intercargo_block_class_tokens(array $block): array
{
    $classes = (string) ($block['attrs']['className'] ?? '');
    return array_values(array_filter(preg_split('#\s+#', trim($classes)) ?: []));
}

/**
 * Expand `repeat` counts so a fixed-cardinality collection is compared exactly.
 */
function intercargo_expand_schema_nodes(array $nodes): array
{
    $expanded = [];
    foreach ($nodes as $node) {
        $times = max(1, (int) ($node['repeat'] ?? 1));
        unset($node['repeat']);
        for ($index = 0; $index < $times; $index++) {
            $expanded[] = $node;
        }
    }
    return $expanded;
}

/**
 * Compare one sibling list against its schema. Order and count are both exact:
 * a reordered or duplicated required block is a failure, not a warning.
 */
function intercargo_validate_block_list(array $blocks, array $nodes, string $path, array &$errors): void
{
    $blocks = intercargo_meaningful_blocks($blocks);
    $nodes = intercargo_expand_schema_nodes($nodes);

    if (count($blocks) !== count($nodes)) {
        $errors[] = sprintf('%s: expected %d block(s), found %d', $path, count($nodes), count($blocks));
        return;
    }

    foreach ($nodes as $index => $node) {
        $block = $blocks[$index];
        $expected = (string) ($node['name'] ?? '');
        $actual = (string) ($block['blockName'] ?? '');
        $here = sprintf('%s/%s[%d]', $path, $expected, $index);

        if ($actual !== $expected) {
            $errors[] = sprintf('%s: expected %s, found %s', $here, $expected, $actual);
            continue;
        }

        $missing = array_diff($node['classes'] ?? [], intercargo_block_class_tokens($block));
        if ($missing !== []) {
            $errors[] = sprintf('%s: missing required class(es) %s', $here, implode(', ', $missing));
        }

        if (! empty($node['open'])) {
            $items = intercargo_meaningful_blocks((array) ($block['innerBlocks'] ?? []));
            if ($items === []) {
                $errors[] = sprintf('%s: an open collection must keep at least one item', $here);
                continue;
            }
            foreach ($items as $position => $item) {
                intercargo_validate_block_list(
                    [$item],
                    [$node['item']],
                    sprintf('%s/item[%d]', $here, $position),
                    $errors
                );
            }
            continue;
        }

        if (array_key_exists('children', $node)) {
            intercargo_validate_block_list((array) ($block['innerBlocks'] ?? []), $node['children'], $here, $errors);
        }
    }
}

/**
 * Rules that hold everywhere in the tree, independent of the declared shape.
 */
function intercargo_validate_tree_rules(array $blocks, array &$errors): void
{
    foreach (intercargo_meaningful_blocks($blocks) as $block) {
        $name = (string) $block['blockName'];

        if (in_array($name, intercargo_composition_forbidden_blocks(), true)) {
            $errors[] = 'forbidden block: ' . $name;
        }

        $html = (string) ($block['innerHTML'] ?? '');
        if (preg_match_all('#<a\b[^>]*>#i', $html, $matches) > 0) {
            foreach ($matches[0] as $tag) {
                intercargo_validate_anchor_tag($tag, $name, $errors);
            }
        }

        intercargo_validate_tree_rules((array) ($block['innerBlocks'] ?? []), $errors);
    }
}

function intercargo_validate_anchor_tag(string $tag, string $context, array &$errors): void
{
    if (preg_match('#href\s*=\s*"([^"]*)"#i', $tag, $href) === 1) {
        if (! intercargo_is_allowed_url($href[1])) {
            $errors[] = sprintf('%s: rejected URL %s', $context, $href[1]);
        }
    }

    if (preg_match('#target\s*=\s*"([^"]*)"#i', $tag, $target) !== 1) {
        return;
    }

    if (! in_array($target[1], intercargo_allowed_link_targets(), true)) {
        $errors[] = sprintf('%s: rejected link target %s', $context, $target[1]);
        return;
    }

    if ($target[1] === '_blank' && preg_match('#rel\s*=\s*"[^"]*noopener[^"]*"#i', $tag) !== 1) {
        $errors[] = sprintf('%s: _blank link without rel="noopener noreferrer"', $context);
    }
}

/**
 * Validate a stored tree against a list of schema nodes.
 *
 * @return string[] Empty when the tree is acceptable.
 */
function intercargo_validate_composition_nodes(array $blocks, array $nodes): array
{
    $errors = [];
    intercargo_validate_block_list($blocks, $nodes, 'section', $errors);
    intercargo_validate_tree_rules($blocks, $errors);
    return $errors;
}

/**
 * Fail-closed entry point for a section renderer.
 *
 * Returns true when the section may render its stored native content. A section
 * with no declared schema is not yet gated and keeps its legacy behaviour.
 */
function intercargo_section_composition_is_valid(string $slug, array $blocks): bool
{
    $nodes = intercargo_composition_schema($slug);
    if ($nodes === null) {
        return true;
    }
    if (intercargo_validate_composition_nodes($blocks, $nodes) === []) {
        return true;
    }
    foreach (intercargo_legacy_composition_schemas($slug) as $legacy_nodes) {
        if (intercargo_validate_composition_nodes($blocks, $legacy_nodes) === []) {
            return true;
        }
    }
    return false;
}

/**
 * Record why a stored composition was rejected.
 *
 * Visitors get a section that simply does not render; the reason belongs in the log,
 * where the site owner can find it, not on the page.
 */
function intercargo_log_composition_failure(string $slug, array $blocks): void
{
    if (! defined('WP_DEBUG') || ! WP_DEBUG) {
        return;
    }
    $nodes = intercargo_composition_schema($slug);
    if ($nodes === null) {
        return;
    }
    $errors = intercargo_validate_composition_nodes($blocks, $nodes);
    if ($errors === []) {
        return;
    }
    error_log(sprintf('[intercargo] %s composition rejected: %s', $slug, implode(' | ', $errors)));
}
