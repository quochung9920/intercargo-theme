# Release Process

A release is produced from source; `themes/intercargo/` is not edited manually.

1. Run `npm run build`.
2. Run `npm run validate`.
3. Run `npm run release`.
4. Deploy the ZIP from `release/` or the standalone `themes/intercargo/` directory.

The release archive uses the historical WordPress theme directory `intercargo-vite` so it can replace an existing installation in place.

## Standalone guarantee

A valid generated theme contains its own PHP runtime, block packages, project assets and built global assets. It has no runtime references to `packages/`, `projects/` or workspace tooling.
