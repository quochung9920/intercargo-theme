<?php
/** Shared runtime helpers for self-contained section packages. */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** Return a unique DOM id while preserving the first occurrence verbatim. */
function intercargo_section_id(string $base): string
{
    static $counts = [];
    $base = trim($base);
    if ($base === '') {
        $base = 'section';
    }
    $counts[$base] = ($counts[$base] ?? 0) + 1;
    return $counts[$base] === 1 ? $base : $base . '-' . $counts[$base];
}
