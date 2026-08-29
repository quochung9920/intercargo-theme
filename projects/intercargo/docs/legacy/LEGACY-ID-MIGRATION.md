# V3.9 canonical block ID migration

V3.9 completes the namespace migration for all nine top-level sections.

The four historical identifiers:

- `acf/intercargo-reasons`
- `acf/intercargo-statement`
- `acf/intercargo-process`
- `acf/intercargo-enquiry`

now map to:

- `intercargo/reasons`
- `intercargo/statement`
- `intercargo/process`
- `intercargo/enquiry`

## Safety model

The old ACF block registrations remain loadable but hidden from the inserter, so
historical content never becomes an unknown block.

Each canonical section declares its historical IDs in its own `block.json` under
`intercargo.legacyNames`. The theme derives the migration map from that metadata;
there is no central per-section registry to maintain.

When an old page is opened in Gutenberg, the editor replaces historical block IDs
with canonical blocks in memory while preserving the saved attributes. WordPress
persists the canonical ID on the next ordinary Save/Update.

A server-side save filter provides the same ID-only rewrite as a fallback for posts,
revisions and synced patterns that are saved without going through the normal page
editor. It changes the serialized block name only; it does not rewrite section data,
markup, CSS, or frontend rendering.

V4 removed the historical `/blocks` packages. Hidden PHP aliases now preserve the declared legacy IDs without loading ACF; they can remain until stored content has naturally migrated.
