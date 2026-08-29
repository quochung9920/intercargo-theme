# Agent Contract

This repository is a design-platform workspace.

## Source of truth

For Intercargo design/content-presentation work, edit only `projects/intercargo/` unless the task explicitly changes platform architecture.

- Visual code: `projects/intercargo/design/`
- Stable project assets: `projects/intercargo/assets/`
- WordPress shell: `projects/intercargo/theme/`
- Global Vite bridge/source: `projects/intercargo/src/`
- Project QA/contracts: `projects/intercargo/qa/`

## Platform code

- Portable contracts: `packages/design-core/`
- WordPress/Gutenberg runtime: `packages/wordpress-adapter/`
- Workspace build/release tooling: `tools/`

Do not put Intercargo branding, sections, assets or WordPress APIs in `packages/design-core/`.

## Generated theme

`themes/intercargo/` is generated output. Do not hand-edit it. Run `npm run sync` or `npm run build` instead.

The generated theme must remain standalone: no runtime import/require/reference may point to `packages/` or `projects/`.

## Compatibility

Never rename canonical saved Gutenberg block IDs such as `intercargo/hero` or rebuild saved block trees from default templates merely because filesystem paths move.

## Quality gate

Run `npm run build` and `npm run validate` before release. Use `npm run release` for a production ZIP.
