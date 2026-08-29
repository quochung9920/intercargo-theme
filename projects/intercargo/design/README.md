# Design Layer

This is the primary workspace for website-specific changes and LLM-generated design work.

## Structure

```text
design/
├── header/
├── footer/
├── global/
│   ├── css/
│   ├── js/
│   └── fonts/
├── sections/
├── components/
└── patterns/
```

All visual section packages live in `design/sections/`. Most content sections may be promoted to synced patterns; page-derived infrastructure such as `service-navigation` explicitly opts out of syncing.

Shared UI primitives and the provider-neutral Form block live in `design/components/`.

## Rules for AI / developers

1. Prefer editing only the target section/component.
2. Do not modify block IDs for existing sections.
3. Do not rebuild existing InnerBlocks from defaults when editing saved content.
4. Do not add section/component names to central registries; discovery is folder-based.
5. Preserve approved frontend classes/DOM unless a reviewed design change requires otherwise.
6. Use `npm run validate` before packaging a release.
7. Root `src/` files are stable Vite bridges to `design/global/`; do not put website logic there.
8. Root `patterns/` files are stable WordPress proxies. Edit pattern bodies under `design/patterns/`.
