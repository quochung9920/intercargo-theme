# Intercargo Design Platform

This repository is now a workspace, not a WordPress theme root.

## Architecture

```text
packages/design-core/        portable design contracts
packages/wordpress-adapter/  WordPress/Gutenberg runtime source
projects/intercargo/         Intercargo design + theme source of truth
themes/intercargo/           generated standalone WordPress theme
tools/                       workspace build/release/architecture tooling
```

The dependency direction is one-way:

```text
design-core -> project source -> WordPress adapter/export -> standalone theme
```

`themes/intercargo/` is deployable by itself. It must not load `packages/` or `projects/` at runtime. Keep canonical `intercargo/*` Gutenberg block IDs stable.

## Commands

- `npm run sync` — regenerate theme source/runtime without rebuilding Vite assets.
- `npm run build` — regenerate the theme and rebuild global Vite assets.
- `npm run validate` — validate architecture plus the exported theme contracts.
- `npm run release` — build, validate and create a production ZIP under `release/`.

Edit `projects/intercargo/`, not `themes/intercargo/`.
