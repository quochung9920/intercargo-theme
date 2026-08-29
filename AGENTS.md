# Agent Contract

This repository is being separated into a portable Design Core, project-specific design source and a standalone WordPress theme output. Read `workspace.json` and `docs/DESIGN-CORE-SEPARATION.md` before architectural work.

## Current migration phase

`workspace.json` is authoritative for the migration phase. In **phase 1**, the existing `/design` directory remains the visual authority and the existing root WordPress theme remains the runnable baseline. Phase 1 architecture work must not change frontend markup, CSS or JavaScript behavior.

## Target boundaries

- `packages/design-core/` — portable, brand- and CMS-independent contracts/build tooling. No WordPress runtime APIs and no project-specific design.
- `packages/wordpress-adapter/` — generic WordPress/Gutenberg integration. No project branding or project-specific styling.
- `projects/intercargo/` — future Intercargo visual source of truth: tokens, global presentation, components, sections, patterns, shell and assets.
- `themes/intercargo/` — generated/deployable standalone theme output. Do not hand-edit generated runtime/design files here.

## Legacy phase-1 boundaries

For normal site/design tasks while migration phase is 1, continue working in `/design` only:

- `/design/header`
- `/design/footer`
- `/design/global`
- `/design/sections/<target>`
- `/design/components/<target>`
- `/design/patterns`

Treat `/inc`, `/tests`, `/tools`, root WordPress template proxies, `/src`, `functions.php`, `theme.json` and `vite.config.js` as framework/core unless the task explicitly requires architecture changes.

## Stability rules

- Canonical Gutenberg block IDs such as `intercargo/hero` and `intercargo/service-flow` must remain stable across filesystem moves.
- Never rebuild already-saved page content from `template.json` merely because a package moved.
- Never add a new section to a PHP/JS slug registry; package discovery remains metadata/filesystem driven.
- Shared content continues to use WordPress Core `wp_block` records; never hard-code shared numeric IDs.
- A released theme must not require or import files from `packages/` or `projects/` at runtime.

Run `npm run validate` before release.
