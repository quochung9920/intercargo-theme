<?php
declare(strict_types=1);

$root = dirname(__DIR__);
define('ABSPATH', $root . '/');

// Minimal WordPress shims: this test intentionally exercises package discovery and
// composition metadata without booting a WordPress installation.
function add_filter(...$args): bool { return true; }
function add_action(...$args): bool { return true; }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
function get_theme_file_path(string $file = ''): string {
    $base = dirname(__DIR__);
    return $file === '' ? $base : $base . '/' . ltrim($file, '/');
}
function get_theme_file_uri(string $file = ''): string { return 'https://theme.test/' . ltrim($file, '/'); }
function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false { return json_encode($value, $flags, $depth); }
function wp_script_is(...$args): bool { return false; }
function wp_register_script(...$args): bool { return true; }
function wp_add_inline_script(...$args): bool { return true; }
function wp_enqueue_script(...$args): void {}
function wp_strip_all_tags(string $value): string { return strip_tags($value); }
function get_bloginfo(string $show = ''): string { return $show === 'charset' ? 'UTF-8' : ''; }
function sanitize_html_class(string $value, string $fallback = ''): string {
    $clean = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    return is_string($clean) && $clean !== '' ? $clean : $fallback;
}
class WP_Post {
    public int $ID = 0;
    public string $post_type = '';
    public string $post_content = '';
    public function __construct(int $id = 0, string $type = '', string $content = '') { $this->ID=$id; $this->post_type=$type; $this->post_content=$content; }
}
$GLOBALS['intercargo_test_posts'] = [];
function get_post(int $post_id): ?WP_Post { return $GLOBALS['intercargo_test_posts'][$post_id] ?? null; }
function parse_blocks(string $content): array {
    preg_match_all('#<!--\\s+wp:([a-z0-9-]+/[a-z0-9-]+)(?:\\s+\\{.*?\\})?\\s*/?-->#s', $content, $matches);
    return array_map(static fn($name) => ['blockName' => $name], $matches[1] ?? []);
}

require_once $root . '/inc/block-packages.php';
require_once $root . '/inc/section-runtime.php';
require_once $root . '/inc/composition.php';
require_once $root . '/inc/shared-sections.php';
require_once $root . '/inc/migrations-4112.php';
require_once $root . '/design/sections/service-navigation/bootstrap.php';

$contracts = json_decode((string) file_get_contents(__DIR__ . '/section-contracts.json'), true, 512, JSON_THROW_ON_ERROR);
$errors = [];

function fail_if(bool $condition, string $message, array &$errors): void {
    if ($condition) $errors[] = $message;
}

$expectedSections = array_keys($contracts['topLevelSections']);
$actualSections = [];
foreach (intercargo_discover_block_packages() as $dir) {
    $meta = intercargo_block_package_metadata($dir);
    if (! is_array($meta)) continue;
    $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
    if (($cfg['sectionPackage'] ?? false) === true && isset($meta['name'])) {
        $actualSections[] = (string) $meta['name'];
    }
}
$actualSections = array_values(array_unique($actualSections));
sort($expectedSections); sort($actualSections);
fail_if($actualSections !== $expectedSections, 'Top-level section set changed: expected ' . json_encode($expectedSections) . ', got ' . json_encode($actualSections), $errors);

$discovered = intercargo_discovered_block_package_names();
foreach ($expectedSections as $name) {
    fail_if(! in_array($name, $discovered, true), "Package discovery missed {$name}", $errors);
}

$definitions = intercargo_package_editor_definitions();

fail_if(is_dir($root . '/sections'), 'Obsolete root sections/ directory still exists', $errors);
fail_if(is_dir($root . '/components'), 'Obsolete root components/ directory still exists', $errors);

foreach ($contracts['topLevelSections'] as $canonicalName => $sectionContract) {
    $defaultPath = 'design/sections/' . substr($canonicalName, strlen('intercargo/'));
    $packagePath = (string) ($sectionContract['packagePath'] ?? $defaultPath);
    fail_if(! is_file($root . '/' . ltrim($packagePath, '/') . '/block.json'), $canonicalName . ' is missing from ' . $packagePath, $errors);
}

foreach ($contracts['topLevelSections'] as $name => $contract) {
    $defaultPath = 'design/sections/' . substr($name, strlen('intercargo/'));
    $packagePath = (string) ($contract['packagePath'] ?? $defaultPath);
    $dir = $root . '/' . ltrim($packagePath, '/');
    fail_if(! is_file($dir . '/block.json'), "{$name} package missing at {$packagePath}", $errors);
    if (! is_file($dir . '/block.json')) continue;
    $meta = json_decode((string) file_get_contents($dir . '/block.json'), true);
    $supports = is_array($meta['supports'] ?? null) ? $meta['supports'] : [];
    $expectedSyncable = ($contract['syncable'] ?? true) === true;
    fail_if(($supports['reusable'] ?? null) !== $expectedSyncable, $expectedSyncable ? "{$name} must remain syncable/reusable" : "{$name} must remain local/non-reusable", $errors);
    fail_if(($supports['multiple'] ?? null) !== true, "{$name} must remain repeatable (supports.multiple=true)", $errors);

    if ($contract['editor'] === 'composition') {
        $slug = $contract['compositionSlug'];
        fail_if(($meta['editorScript'] ?? null) !== 'intercargo-package-editor', "{$name} lost generic package editor registration", $errors);
        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        fail_if(($cfg['packageType'] ?? null) !== 'section', "{$name} lost packageType=section", $errors);
        fail_if(($cfg['compositionSlug'] ?? null) !== $slug, "{$name} compositionSlug mismatch", $errors);
        fail_if(! isset($definitions[$name]), "{$name} missing from intercargoPackageDefinitions", $errors);
        $data = intercargo_composition_data($slug);
        fail_if(! is_array($data) || ! is_array($data['template'] ?? null) || $data['template'] === [], "{$name} composition template failed to load", $errors);
    } else {
        $ref = (string) ($meta['editorScript'] ?? '');
        fail_if(! str_starts_with($ref, 'file:./editor.js'), "{$name} custom editorScript changed unexpectedly", $errors);
        fail_if(! is_file($dir . '/editor.js'), "{$name} missing local editor.js", $errors);
    }
}

foreach ($contracts['compositionItems'] as $name => $contract) {
    fail_if(! in_array($name, $discovered, true), "Composition item {$name} not discovered", $errors);
    $slug = $contract['compositionSlug'];
    fail_if(intercargo_composition_data($slug) === null, "Composition item {$name} template not loadable", $errors);
    fail_if(! isset($definitions[$name]), "Composition item {$name} missing editor definition", $errors);
    if (isset($contract['packagePath'])) {
        $itemDir = $root . '/' . ltrim((string) $contract['packagePath'], '/');
        fail_if(! is_file($itemDir . '/block.json'), "Composition item {$name} missing at {$contract['packagePath']}", $errors);
        $itemMeta = is_file($itemDir . '/block.json') ? json_decode((string) file_get_contents($itemDir . '/block.json'), true) : null;
        $expectedAncestor = (string) ($contract['ancestor'] ?? '');
        $ancestors = is_array($itemMeta['ancestor'] ?? null) ? $itemMeta['ancestor'] : [];
        fail_if($expectedAncestor !== '' && ! in_array($expectedAncestor, $ancestors, true), "Composition item {$name} ancestor contract changed", $errors);
        $expectedCategory = (string) ($contract['category'] ?? 'intercargo-section-items');
        fail_if(($itemMeta['category'] ?? null) !== $expectedCategory, "Composition item {$name} must stay in {$expectedCategory}", $errors);
        $expectedInserter = array_key_exists('inserter', $contract) ? (bool) $contract['inserter'] : true;
        fail_if(($itemMeta['supports']['inserter'] ?? null) !== $expectedInserter, "Composition item {$name} inserter contract changed", $errors);
    }
}

foreach ($contracts['nonReusableComponents'] as $name) {
    $found = false;
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir);
        if (($meta['name'] ?? '') !== $name) continue;
        $found = true;
        fail_if(($meta['supports']['reusable'] ?? null) !== false, "{$name} must not be independently reusable", $errors);
        break;
    }
    fail_if(! $found, "Expected component/item {$name} not discovered", $errors);
}


// Collection editing contract: repeating content is represented by named item blocks
// in List View. The section shell stays locked, while only the declared collection
// container is open for duplicate/delete/reorder/add operations.
foreach ([
    'services' => ['className' => 'services-panel', 'allows' => 'intercargo/service-card'],
    'locations' => ['className' => 'location-list', 'allows' => 'intercargo/location'],
    'reasons' => ['className' => 'content-columns-grid', 'allows' => 'intercargo/content-column'],
    'service-options' => ['className' => 'service-options-panel', 'allows' => 'intercargo/service-option'],
    'service-pricing' => ['className' => 'service-pricing-calc', 'allows' => 'intercargo/pricing-row'],
] as $slug => $collectionContract) {
    $definition = intercargo_composition_data($slug);
    fail_if(($definition['editorTemplateLock'] ?? null) !== 'all', ucfirst($slug) . ' must use structural editor mode rather than contentOnly flattening', $errors);
    $collections = is_array($definition['openCollections'] ?? null) ? $definition['openCollections'] : [];
    $matched = false;
    foreach ($collections as $collection) {
        if (($collection['className'] ?? '') === $collectionContract['className'] && ($collection['allows'] ?? '') === $collectionContract['allows']) {
            $matched = true;
            break;
        }
    }
    fail_if(! $matched, ucfirst($slug) . ' repeatable collection contract is missing', $errors);
}

$expectedLegacy = [];
foreach ($contracts['topLevelSections'] as $canonical => $contract) {
    foreach (($contract['legacyNames'] ?? []) as $legacy) $expectedLegacy[$legacy] = $canonical;
}
ksort($expectedLegacy);
$actualLegacy = intercargo_block_package_legacy_name_map();
fail_if($actualLegacy !== $expectedLegacy, 'Legacy ID map changed: expected ' . json_encode($expectedLegacy) . ', got ' . json_encode($actualLegacy), $errors);

// Saved-content migration fixture: only the block comment ID may change.
$fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/legacy-saved-content.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($fixture['cases'] as $case) {
    $attrs = json_encode($case['attributes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $before = '<!-- wp:' . $case['legacy'] . ' ' . $attrs . ' /-->';
    $after = intercargo_migrate_legacy_block_ids_in_content($before);
    $expected = '<!-- wp:' . $case['canonical'] . ' ' . $attrs . ' /-->';
    fail_if($after !== $expected, 'Legacy saved-content migration altered data for ' . $case['legacy'], $errors);
}



// Shared-section foundation: local identity is section type; shared identity is wp_block ref.
$sharedTypes = intercargo_syncable_section_types();
$expectedSharedTypes = [];
foreach ($contracts['topLevelSections'] as $name => $contract) {
    if (($contract['syncable'] ?? true) === true) $expectedSharedTypes[] = substr($name, strlen('intercargo/'));
}
sort($expectedSharedTypes, SORT_STRING);
fail_if(array_keys($sharedTypes) !== $expectedSharedTypes, 'Shared section type registry changed: ' . json_encode(array_keys($sharedTypes)), $errors);
fail_if(intercargo_shared_section_type_from_content('<!-- wp:intercargo/services /-->') !== 'services', 'Shared Services content type was not inferred', $errors);
fail_if(intercargo_shared_section_type_from_content('<!-- wp:intercargo/hero /-->') !== 'hero', 'Shared Hero content type was not inferred', $errors);

// Repeatable sections retain the historical anchor for the first instance and
// suffix later instances, preventing duplicate IDs without breaking old links.
$repeatBase = 'repeatable-contract';
fail_if(intercargo_section_id($repeatBase) !== $repeatBase, 'First repeatable section ID changed unexpectedly', $errors);
fail_if(intercargo_section_id($repeatBase) !== $repeatBase . '-2', 'Second repeatable section ID was not uniquified', $errors);

$futureSectionMeta = intercargo_enable_section_synced_pattern_support([
    'name' => 'intercargo/future-section',
    'intercargo' => ['sectionPackage' => true, 'syncable' => true],
    'supports' => [],
]);
fail_if(($futureSectionMeta['supports']['multiple'] ?? null) !== true, 'Future section packages are not automatically repeatable', $errors);
fail_if(($futureSectionMeta['supports']['reusable'] ?? null) !== true, 'Future section packages are not automatically syncable', $errors);
fail_if(intercargo_shared_section_type_from_content('<!-- wp:intercargo/services /--><!-- wp:intercargo/faq /-->') !== null, 'Multi-section content must not be accepted as one shared section', $errors);
fail_if(intercargo_shared_section_type_from_content('<!-- wp:core/paragraph /-->') !== null, 'Non-Intercargo content must not be accepted as a shared section', $errors);
fail_if(! function_exists('intercargo_shared_section_usage'), 'Shared section usage resolver is missing', $errors);
fail_if(! function_exists('intercargo_content_references_shared_section'), 'Shared section reference scanner is missing', $errors);

// Dynamic Service Navigation: the block is page-derived, never a manual or synced tab list.
$navMeta = json_decode((string) file_get_contents($root . '/design/sections/service-navigation/block.json'), true);
fail_if(($navMeta['intercargo']['syncable'] ?? null) !== false, 'Service Navigation must explicitly opt out of synced patterns', $errors);
fail_if(($navMeta['supports']['reusable'] ?? null) !== false, 'Service Navigation supports.reusable must be false', $errors);
fail_if(is_dir($root . '/design/sections/service-navigation/blocks'), 'Manual service-jump-link child package still exists', $errors);
fail_if(in_array('intercargo/service-jump-link', $discovered, true), 'Obsolete manual Service Jump Link is still discoverable', $errors);


// 4.11.2 cleanup: Service Criteria was a duplicate Content Columns model and is
// removed completely rather than retained as an invisible compatibility package.
fail_if(is_dir($root . '/design/sections/service-criteria'), 'Removed Service Criteria package directory still exists', $errors);
fail_if(in_array('intercargo/service-criteria', $discovered, true), 'Removed Service Criteria block is still discoverable', $errors);
fail_if(in_array('intercargo/service-criterion', $discovered, true), 'Removed Service Criterion item is still discoverable', $errors);
fail_if(! in_array('intercargo/content-column', $discovered, true), 'Content Column item is not discoverable', $errors);
$contentColumnMeta = json_decode((string) file_get_contents($root . '/design/sections/reasons/blocks/content-column/block.json'), true);
fail_if(($contentColumnMeta['category'] ?? null) !== 'intercargo-section-items', 'Content Column must live in the Section Items category', $errors);
fail_if(! in_array('intercargo/reasons', (array) ($contentColumnMeta['ancestor'] ?? []), true), 'Content Column must be scoped to Content Columns', $errors);

$removedMarkup = '<!-- wp:intercargo/service-criteria {"serviceNavTitle":"Right call"} -->'
    . '<!-- wp:group {"className":"container service-criteria-content"} -->'
    . '<div class="wp-block-group container service-criteria-content">'
    . '<!-- wp:heading {"className":"service-criteria-heading"} --><h2 class="wp-block-heading service-criteria-heading">When air is the right call.</h2><!-- /wp:heading -->'
    . '<!-- wp:group {"className":"service-criteria-grid"} --><div class="wp-block-group service-criteria-grid">'
    . '<!-- wp:intercargo/service-criterion --><!-- wp:heading {"className":"service-criterion-title"} --><h3 class="wp-block-heading service-criterion-title">Deadline</h3><!-- /wp:heading --><!-- /wp:intercargo/service-criterion -->'
    . '</div><!-- /wp:group --></div><!-- /wp:group -->'
    . '<!-- /wp:intercargo/service-criteria -->';
$removedMigrated = intercargo_migrate_service_criteria_markup_4112($removedMarkup);
fail_if(str_contains($removedMigrated, 'intercargo/service-criteria'), '4.11.2 migration left Service Criteria identity in stored markup', $errors);
fail_if(str_contains($removedMigrated, 'intercargo/service-criterion'), '4.11.2 migration left Service Criterion identity in stored markup', $errors);
fail_if(! str_contains($removedMigrated, 'wp:intercargo/reasons'), '4.11.2 migration did not convert Service Criteria to Content Columns', $errors);
fail_if(! str_contains($removedMigrated, 'wp:intercargo/content-column'), '4.11.2 migration did not convert criteria rows to Content Column items', $errors);
fail_if(! str_contains($removedMigrated, '"theme":"service-paper"'), '4.11.2 migration did not preserve the service-paper presentation', $errors);

// 4.11.3: service-paper and service-white are one structural design system.
$contentColumnsCss = (string) file_get_contents($root . '/design/sections/reasons/style.css');
$contentColumnsTemplate = (string) file_get_contents($root . '/design/sections/reasons/template.json');
fail_if(substr_count($contentColumnsCss, '--content-columns-card-title:') !== 1, 'Content Columns service themes define divergent column-title sizes', $errors);
fail_if(str_contains($contentColumnsTemplate, 'section-head-gap'), 'Content Columns still serializes the global section-head-gap utility', $errors);
fail_if(! str_contains($contentColumnsCss, '--content-columns-grid-top: var(--section-gap);'), 'Content Columns no longer follows the global section-gap rhythm', $errors);
fail_if(! str_contains($contentColumnsCss, 'margin: var(--content-columns-grid-top) 0 0 !important;'), 'Content Columns does not guard against WordPress flow-layout margin stacking', $errors);
// 4.11.5: old saved core/group markup may still contain section-head-gap.
$contentColumnsRender = (string) file_get_contents($root . '/design/sections/reasons/render.php');
$contentColumnsEditor = (string) file_get_contents($root . '/design/sections/reasons/editor.js');
fail_if(str_contains($contentColumnsRender, 'content-columns-content section-head-gap content-columns-layout'), 'Content Columns legacy renderer still emits section-head-gap', $errors);
fail_if(! str_contains($contentColumnsCss, '.content-columns .content-columns-layout'), 'Content Columns does not scope the legacy gap reset strongly enough to beat global.css', $errors);
fail_if(! str_contains($contentColumnsCss, 'gap: 0;'), 'Content Columns does not neutralize legacy section-head-gap gap', $errors);
fail_if(! str_contains($contentColumnsEditor, "token !== 'section-head-gap'"), 'Content Columns editor does not persistently remove legacy section-head-gap from saved core/group markup', $errors);
// 4.12.1: each service Content Columns section keeps symmetric theme spacing.
// The Air Freight source uses `padding-block` per section; adjacency must never
// reduce only one edge and visually push content off-centre.
fail_if(str_contains($contentColumnsCss, 'padding-bottom: calc(var(--section-y) / 2);'), 'Content Columns still halves bottom padding when adjacent to another service section', $errors);
fail_if(str_contains($contentColumnsCss, 'padding-top: calc(var(--section-y) / 2);'), 'Content Columns still halves top padding when adjacent to another service section', $errors);
fail_if(str_contains($contentColumnsCss, ':has(+ .content-columns:is(.content-columns--service-paper, .content-columns--service-white))'), 'Content Columns still contains the removed adjacency spacing hack', $errors);


$eligibleMeta = intercargo_service_navigation_extend_section_metadata([
    'name' => 'intercargo/services',
    'intercargo' => ['sectionPackage' => true],
]);
fail_if(! isset($eligibleMeta['attributes']['serviceNavTitle']), 'Eligible sections did not receive serviceNavTitle metadata', $errors);
fail_if(! isset($eligibleMeta['attributes']['serviceNavHidden']), 'Eligible sections did not receive serviceNavHidden metadata', $errors);
fail_if(! isset($eligibleMeta['attributes']['serviceNavKey']), 'Eligible sections did not receive stable serviceNavKey metadata', $errors);
fail_if(! isset($eligibleMeta['attributes']['sectionAnchor']), 'Eligible sections did not receive the optional fixed sectionAnchor metadata', $errors);
fail_if(! isset($navMeta['attributes']['tabOrder']), 'Service Navigation is missing page-local tabOrder', $errors);
$heroMeta = intercargo_service_navigation_extend_section_metadata([
    'name' => 'intercargo/service-hero',
    'intercargo' => ['sectionPackage' => true, 'serviceNavigation' => false],
]);
fail_if(isset($heroMeta['attributes']['serviceNavTitle']), 'Service Hero must not receive Service Navigation controls', $errors);

$dynamicItems = intercargo_service_navigation_collect_from_blocks([
    ['blockName' => 'intercargo/service-hero', 'attrs' => []],
    ['blockName' => 'intercargo/service-navigation', 'attrs' => []],
    [
        'blockName' => 'intercargo/services',
        'attrs' => [],
        'innerBlocks' => [[
            'blockName' => 'core/group',
            'attrs' => [],
            'innerBlocks' => [[
                'blockName' => 'core/heading',
                'attrs' => [],
                'innerHTML' => '<h2>Which service?</h2>',
                'innerBlocks' => [],
            ]],
        ]],
    ],
    ['blockName' => 'intercargo/reasons', 'attrs' => ['title' => 'Why importers switch', 'serviceNavTitle' => 'Why us', 'serviceNavKey' => 'why-us-key', 'sectionAnchor' => 'why-fixed'], 'innerBlocks' => []],
    [
        'blockName' => 'intercargo/faq',
        'attrs' => ['serviceNavHidden' => true],
        'innerBlocks' => [[
            'blockName' => 'core/heading',
            'attrs' => [],
            'innerHTML' => '<h2>Questions</h2>',
            'innerBlocks' => [],
        ]],
    ],
    [
        'blockName' => 'intercargo/services',
        'attrs' => [],
        'innerBlocks' => [[
            'blockName' => 'core/heading',
            'attrs' => ['content' => 'Second services section'],
            'innerHTML' => '',
            'innerBlocks' => [],
        ]],
    ],
]);
fail_if(count($dynamicItems) !== 3, 'Dynamic Service Navigation did not derive exactly the visible sections', $errors);
fail_if(($dynamicItems[0]['label'] ?? '') !== 'Which service?', 'Service Navigation did not derive first visible heading', $errors);
fail_if(($dynamicItems[0]['anchor'] ?? '') !== 'services', 'Service Navigation derived the wrong Services target', $errors);
fail_if(($dynamicItems[1]['label'] ?? '') !== 'Why us', 'Sub-navigation title override did not win over section heading', $errors);
fail_if(($dynamicItems[1]['anchor'] ?? '') !== 'why-fixed', 'Fixed section target was not respected', $errors);
fail_if(($dynamicItems[1]['key'] ?? '') !== 'why-us-key', 'Stable section navigation key was not preserved', $errors);
fail_if(($dynamicItems[2]['anchor'] ?? '') !== 'services-2', 'Duplicate section target did not match runtime -2 suffix contract', $errors);
$reorderedItems = intercargo_service_navigation_apply_order($dynamicItems, [
    (string) ($dynamicItems[2]['key'] ?? ''),
    'why-us-key',
]);
fail_if(($reorderedItems[0]['anchor'] ?? '') !== 'services-2', 'Page-local Service Navigation drag order was not applied', $errors);
fail_if(($reorderedItems[1]['anchor'] ?? '') !== 'why-fixed', 'Page-local Service Navigation order lost the fixed target item', $errors);
fail_if(count($reorderedItems) !== 3, 'Service Navigation order dropped newly discovered sections', $errors);
$navEditorSource = (string) file_get_contents($root . '/design/sections/service-navigation/editor.js');
fail_if(str_contains($navEditorSource, "label: 'Target ID'"), 'Compact Service Navigation manager must not expose Target ID', $errors);
fail_if(str_contains($navEditorSource, "label: 'Tab title'"), 'Compact Service Navigation manager must not duplicate Tab title below the row', $errors);
fail_if(! str_contains($navEditorSource, 'intercargo-service-nav-manager__title-input'), 'Compact Service Navigation inline title input is missing', $errors);
fail_if(! str_contains($navEditorSource, 'Targets are generated automatically'), 'Service Navigation no longer communicates automatic target behavior', $errors);

if ($errors) {
    fwrite(STDERR, implode("\n", array_map(static fn($e) => 'ERROR: ' . $e, $errors)) . "\n");
    exit(1);
}

$compositionEditorCount = count(array_filter($contracts['topLevelSections'], static fn(array $c): bool => ($c['editor'] ?? '') === 'composition'));
$itemEditorCount = count($contracts['compositionItems']);
$legacyMigrationCount = count($expectedLegacy);
echo sprintf(
    "RUNTIME CONTRACT OK: %d sections, %d composition editors, %d item editors, %d legacy migrations.\n",
    count($contracts['topLevelSections']),
    $compositionEditorCount,
    $itemEditorCount,
    $legacyMigrationCount
);
