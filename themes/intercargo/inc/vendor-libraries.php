<?php
/**
 * Pinned third-party frontend library registry.
 *
 * Libraries are registered once and only enqueued when a component or block asks
 * for them. New section packages may declare:
 *   "intercargo": { "libraries": ["swiper", "gsap", "scrolltrigger"] }
 */
declare(strict_types=1);

if (! defined('ABSPATH')) exit;

function intercargo_vendor_library_registry(): array
{
    return [
        'gsap' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/gsap.min.js',
            'version' => '3.15.0',
        ],
        'scrolltrigger' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/ScrollTrigger.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'flip' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/Flip.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'splittext' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/SplitText.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'scrollto' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/ScrollToPlugin.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'motionpath' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/MotionPathPlugin.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'observer' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/Observer.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'draggable' => [
            'script' => 'https://cdn.jsdelivr.net/npm/gsap@3.15.0/dist/Draggable.min.js',
            'version' => '3.15.0',
            'deps' => ['gsap'],
        ],
        'swiper' => [
            'script' => 'https://cdn.jsdelivr.net/npm/swiper@14.1.0/swiper-bundle.min.js',
            'style' => 'https://cdn.jsdelivr.net/npm/swiper@14.1.0/swiper-bundle.min.css',
            'version' => '14.1.0',
        ],
        'lenis' => [
            'script' => 'https://unpkg.com/lenis@1.3.26/dist/lenis.min.js',
            'style' => 'https://unpkg.com/lenis@1.3.26/dist/lenis.css',
            'version' => '1.3.26',
        ],
        'lottie' => [
            'script' => 'https://unpkg.com/lottie-web@5.13.0/build/player/lottie.min.js',
            'version' => '5.13.0',
        ],
    ];
}

function intercargo_vendor_handle(string $name): string
{
    return 'intercargo-vendor-' . sanitize_key($name);
}

function intercargo_register_vendor_libraries(): void
{
    foreach (intercargo_vendor_library_registry() as $name => $config) {
        $handle = intercargo_vendor_handle($name);
        $dependencies = [];
        foreach ((array) ($config['deps'] ?? []) as $dep) {
            $dependencies[] = intercargo_vendor_handle((string) $dep);
        }
        if (! empty($config['style']) && ! wp_style_is($handle, 'registered')) {
            wp_register_style($handle, (string) $config['style'], [], (string) ($config['version'] ?? null));
        }
        if (! empty($config['script']) && ! wp_script_is($handle, 'registered')) {
            wp_register_script(
                $handle,
                (string) $config['script'],
                $dependencies,
                (string) ($config['version'] ?? null),
                true
            );
        }
    }
}
add_action('init', 'intercargo_register_vendor_libraries', 30);

function intercargo_enqueue_vendor_library(string $name): void
{
    $registry = intercargo_vendor_library_registry();
    if (! isset($registry[$name])) return;
    $handle = intercargo_vendor_handle($name);
    foreach ((array) ($registry[$name]['deps'] ?? []) as $dep) intercargo_enqueue_vendor_library((string) $dep);
    if (! empty($registry[$name]['style'])) wp_enqueue_style($handle);
    if (! empty($registry[$name]['script'])) wp_enqueue_script($handle);
}

function intercargo_block_package_library_map(): array
{
    $map = [];
    foreach (intercargo_discover_block_packages() as $dir) {
        $meta = intercargo_block_package_metadata($dir);
        if (! is_array($meta)) continue;
        $name = (string) ($meta['name'] ?? '');
        if ($name === '') continue;
        $cfg = is_array($meta['intercargo'] ?? null) ? $meta['intercargo'] : [];
        $libraries = array_values(array_filter((array) ($cfg['libraries'] ?? []), 'is_string'));
        if ($libraries !== []) $map[$name] = $libraries;
    }
    return $map;
}

function intercargo_collect_block_names(array $blocks, array &$names): void
{
    foreach ($blocks as $block) {
        $name = (string) ($block['blockName'] ?? '');
        if ($name !== '') $names[$name] = true;
        if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            intercargo_collect_block_names($block['innerBlocks'], $names);
        }
    }
}

function intercargo_current_post_uses_intercargo_blocks(): bool
{
    if (! is_singular()) return false;
    $post = get_post();
    if (! $post instanceof WP_Post || trim((string) $post->post_content) === '') return false;
    $names = [];
    intercargo_collect_block_names(parse_blocks((string) $post->post_content), $names);
    foreach (array_keys($names) as $name) if (str_starts_with($name, 'intercargo/') || str_starts_with($name, 'acf/intercargo-')) return true;
    return false;
}

function intercargo_enqueue_declared_vendor_libraries(): void
{
    if (! is_singular()) return;
    $post = get_post();
    if (! $post instanceof WP_Post) return;
    $names = [];
    intercargo_collect_block_names(parse_blocks((string) $post->post_content), $names);
    $map = intercargo_block_package_library_map();
    foreach (array_keys($names) as $name) {
        foreach ((array) ($map[$name] ?? []) as $library) intercargo_enqueue_vendor_library((string) $library);
    }
}
add_action('wp_enqueue_scripts', 'intercargo_enqueue_declared_vendor_libraries', 18);
