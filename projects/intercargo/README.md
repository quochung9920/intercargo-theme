# Intercargo design project

This directory is the source of truth for Intercargo-specific presentation and theme composition.

- `design/` — visual system, components, sections, patterns, header/footer and interactions.
- `assets/` — stable project assets and backwards-compatible media URLs.
- `src/` — Vite bridge entries for global CSS/JS.
- `theme/` — handwritten WordPress shell files that are copied into the generated theme.
- `qa/` — project-specific contracts, visual/runtime tests and validation tools.
- `vite.config.js` — builds global assets directly into `themes/intercargo/dist`.

Do not edit generated files under `themes/intercargo` directly. Run `npm run sync` after source changes and `npm run build` before release.
