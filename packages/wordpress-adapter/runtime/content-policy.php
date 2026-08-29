<?php
/**
 * Content safety policy.
 *
 * Editor locks are UX controls. This module is the runtime boundary: it decides
 * what markup and which URLs may survive in an approved section regardless of
 * how they got there.
 */
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Allowed link targets. Policy of record: ADR D5.
 */
function intercargo_allowed_link_targets(): array
{
    return ['_self', '_blank'];
}

/**
 * Decide whether a stored or rendered URL may be emitted.
 *
 * Allowlist, never a denylist. Anything that does not match a known-good shape
 * is rejected, so novel obfuscations (entity-encoded schemes, embedded control
 * characters, whitespace-split protocols) fail closed by construction rather
 * than by enumeration.
 *
 * Allowed  : https://host/path, http://host/path, /root-relative, #anchor,
 *            mailto:name@host, tel:+123
 * Rejected : javascript: and every other executable scheme, data:, protocol-
 *            relative //host, empty and malformed values.
 */
function intercargo_is_allowed_url(string $url): bool
{
    $url = trim($url);
    if ($url === '' || str_starts_with($url, '//')) {
        return false;
    }
    $patterns = [
        '#^\#[^\s"<>]*$#',                         // anchor
        '#^/(?!/)[^\s"<>]*$#',                     // root-relative
        '#^https?://[^\s"<>]+$#i',                 // absolute http(s)
        '#^mailto:[^\s"<>@]+@[^\s"<>@]+$#i',       // mailto
        '#^tel:\+?[0-9()\s.\-]+$#i',               // tel
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url) === 1) {
            return true;
        }
    }
    return false;
}

/**
 * The rel value a target requires. `_blank` must never be emitted without it.
 */
function intercargo_link_rel_for_target(string $target): string
{
    return $target === '_blank' ? 'noopener noreferrer' : '';
}
