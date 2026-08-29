# Workspace Architecture

The repository is a build workspace with three independent concerns.

```text
packages/design-core/        portable contracts; no WordPress and no Intercargo presentation
packages/wordpress-adapter/  WordPress/Gutenberg runtime source
projects/intercargo/         Intercargo-specific design, assets, shell and QA
themes/intercargo/           generated standalone WordPress theme
```

## Dependency rule

Dependencies move only toward generated output:

```text
design-core -> project -> adapter/export -> theme
```

The generated theme never reaches back to workspace source. Deleting `packages/`, `projects/` and workspace tooling after a successful build must not affect runtime behavior inside `themes/intercargo/`.

## Source identity vs runtime identity

Filesystem locations are build-time implementation details. Saved Gutenberg content is identified by canonical block names such as `intercargo/service-flow`. Moving source files must not rename those block IDs or replace saved InnerBlocks with default templates.

## Project source

`projects/intercargo/design/` is the visual source of truth. It owns global presentation, components, sections, shell presentation and package-local interactions. `projects/intercargo/theme/` owns handwritten WordPress template files. `projects/intercargo/assets/` retains stable project assets and compatibility URLs.

## Adapter

`packages/wordpress-adapter/runtime/` is copied to `inc/` in the generated theme. It is a build-time dependency only.

## Output

`themes/intercargo/` is generated and must be deployable independently. Production ZIPs remove QA-only files but retain runtime assets including `dist/.vite/manifest.json`.
