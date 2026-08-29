# Development Workflow

Edit source, never generated output.

## Design work

Use `projects/intercargo/design/` for sections, components, header/footer presentation, global CSS/JS and templates. Stable assets belong in `projects/intercargo/assets/`.

## Theme shell work

WordPress templates such as `functions.php`, `page.php` and `theme.json` live in `projects/intercargo/theme/`.

## Platform work

Portable contracts live in `packages/design-core/`. WordPress runtime changes live in `packages/wordpress-adapter/`.

## Commands

```bash
npm run sync
npm run build
npm run validate
npm run release
```

`sync` regenerates theme source/runtime while preserving the existing Vite `dist/`. `build` synchronizes first and then rebuilds `dist/`. `validate` checks workspace boundaries and the standalone theme. `release` produces a production ZIP.

Canonical `intercargo/*` block names are compatibility contracts and must remain stable unless an explicit saved-content migration is designed and tested.
