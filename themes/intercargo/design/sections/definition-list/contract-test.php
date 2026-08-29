<?php
/** Contract for the reusable Definition List section. */
declare(strict_types=1);

$root = dirname(__DIR__, 6);
require_once $root . '/wp-load.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$package = __DIR__;
foreach (['block.json', 'render.php', 'template.json', 'style.css'] as $file) {
    $assert(is_file($package . '/' . $file), 'Missing Definition List file: ' . $file);
}
foreach (['block.json', 'render.php', 'template.json'] as $file) {
    $assert(is_file($package . '/blocks/definition-row/' . $file), 'Missing Definition Row file: ' . $file);
}

$template_path = $package . '/template.json';
$template = is_file($template_path) ? json_decode((string) file_get_contents($template_path), true) : null;
$assert(is_array($template), 'Definition List template must be valid JSON.');

$count_named_blocks = static function (array $nodes, string $name) use (&$count_named_blocks): int {
    $count = 0;
    foreach ($nodes as $node) {
        if (! is_array($node)) {
            continue;
        }
        if (($node[0] ?? null) === $name) {
            $count++;
        }
        $count += $count_named_blocks((array) ($node[2] ?? []), $name);
    }
    return $count;
};

if (is_array($template)) {
    $assert($count_named_blocks((array) ($template['template'] ?? []), 'intercargo/definition-row') === 5, 'Definition List default template must contain five reusable rows.');
    $assert(($template['openCollections'][0]['allows'] ?? '') === 'intercargo/definition-row', 'Definition List rows must remain an open collection.');
}

$registered = WP_Block_Type_Registry::get_instance();
$assert($registered->is_registered('intercargo/definition-list'), 'Definition List block must register.');
$assert($registered->is_registered('intercargo/definition-row'), 'Definition Row block must register.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Definition List contract passed.\n";
