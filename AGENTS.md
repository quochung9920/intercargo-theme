# Agent Contract

For normal site/design tasks, work in `/design` only.

## Allowed normal write locations

- `/design/header`
- `/design/footer`
- `/design/global`
- `/design/sections/<target>`
- `/design/components/<target>`
- `/design/patterns`

## Treat as framework/core

Do not modify these unless the task explicitly requires a framework change:

- `/inc`
- `/tests`
- `/tools`
- root WordPress template proxies
- `/src` Vite bridges
- `functions.php`
- `theme.json`
- `vite.config.js`

## New section

Create `design/sections/<slug>/` with its own `block.json` and all section-specific presentation/behavior. The framework auto-discovers it.

Never add a new section to a PHP/JS slug registry.

Run `npm run validate` before release.

## Shared content

Do not duplicate content that is intentionally shared across pages. Use the editor **Reuse & sync** workflow. Shared section records are WordPress Core `wp_block` entities; numeric post IDs are internal implementation details. Never hard-code a shared pattern ID into section source files.
