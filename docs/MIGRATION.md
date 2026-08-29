# Completed Architecture Migration

The former repository root mixed WordPress runtime, Intercargo design source, tests and build tooling. The workspace now separates them.

- Former `/design` -> `projects/intercargo/design`
- Former `/assets` -> `projects/intercargo/assets`
- Former `/src` and `/vite.config.js` -> `projects/intercargo/`
- Former root WordPress shell -> `projects/intercargo/theme`
- Former `/inc` -> `packages/wordpress-adapter/runtime`
- Former `/tests` and project validation -> `projects/intercargo/qa`
- Deployable runtime -> `themes/intercargo`

The generated theme keeps the historical internal layout (`design/`, `inc/`, `assets/`, `dist/`) so existing PHP and block metadata continue to work without changing canonical saved-content identities.
