# Design Core Separation

## Goal

Separate the repository into three independent concerns:

1. **Design Core** — portable, brand/CMS-independent design contracts and build-time tooling.
2. **Project Design** — Intercargo-specific visual implementation and content structure.
3. **Standalone Theme** — deployable WordPress runtime that works without access to Design Core or project source directories.

The dependency direction is one-way:

```text
packages/design-core
        |
projects/intercargo
        |
packages/wordpress-adapter
        |
     build/export
        |
themes/intercargo
```

No runtime dependency may point back upward.

## Non-negotiable invariants

- Existing canonical Gutenberg block IDs (`intercargo/*`) remain stable.
- Saved WordPress block content must not be regenerated from default templates simply because filesystem paths change.
- Phase 1 changes no frontend markup, CSS or JavaScript behavior.
- `packages/design-core` contains no WordPress-specific runtime dependency.
- `packages/wordpress-adapter` contains no Intercargo-specific design.
- The final `themes/intercargo` output is self-contained.
- Production deployments do not need `packages/`, `projects/`, tests or compiler tooling.

## Migration phases

### Phase 1 — boundaries and contracts

Create workspace metadata, portable package boundaries, project/output roots and architecture validation. Keep `/design`, `/inc`, `/src` and the current root theme runtime untouched.

### Phase 2 — project design source

Move the visual authority from `/design` to `projects/intercargo` while preserving canonical block IDs and frontend output. Temporary compatibility bridges are allowed only during the migration.

### Phase 3 — WordPress adapter

Extract generic WordPress/Gutenberg runtime from `/inc` into `packages/wordpress-adapter`. Project-specific behavior remains outside the adapter.

### Phase 4 — compiler/exporter

Generate a complete `themes/intercargo` artifact from project source plus the adapter snapshot. Theme runtime references only paths inside the generated theme.

### Phase 5 — standalone proof

Test the generated theme in isolation with `packages/` and `projects/` unavailable. Fail CI on any runtime path escape or source dependency.

### Phase 6 — cleanup

Remove legacy bridges and obsolete root design/runtime locations after equivalence and migration tests pass.

## Source-of-truth rule

During phase 1, `/design` remains the current visual authority. After the project-source migration, `projects/intercargo` becomes the visual authority. `themes/intercargo` is always an output artifact and must never become the place where design changes are authored.
