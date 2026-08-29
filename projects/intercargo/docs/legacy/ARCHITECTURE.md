# Architecture

The theme separates a stable WordPress/Gutenberg runtime from website-specific design code.

## Stable runtime

`inc/`, root proxies, build bridges, tests and tools own registration, validation, migration, form-provider integration, asset loading and editor safety.

## Design layer

`design/` owns visual markup, CSS, section templates, UI components, interaction presets and global presentation.

## Saved content rule

Filesystem paths are implementation details. Gutenberg content persists by block ID and serialized attributes/InnerBlocks. Moving a package must never change its canonical block ID or replace a saved block tree with its default template.

## Assets

The root `assets/` directory remains intentionally stable for backwards compatibility with already-saved Gutenberg image URLs and brand fallbacks. It should not be moved without a dedicated URL migration.

## Shared content layer

Section folders define design/code identity. Local section content remains in the page. Shared variations use WordPress Core `wp_block` synced-pattern records through `inc/shared-sections.php` and `inc/shared-sections-editor.js`. Never synchronize by block type; synchronization follows the selected shared record only.
