# Shared Sections

## Editor contract


## Hero and every other section type

Hero is not special in the sharing model. It follows the same Local → Save as shared → Reuse → Detach lifecycle as Services. Editors can keep multiple independent Hero sections on one page, or create named shared variations such as `Hero — Homepage`, `Hero — Campaign` and `Hero — Product Launch`.


Every syncable Intercargo content section exposes an open `Reuse & sync` panel. Page-derived infrastructure can opt out; `intercargo/service-navigation` is local because its tab model is generated from the current page sections.

- Local sections show that they affect only the current page and can be saved as a new shared variation or replaced by an existing one.
- Shared sections show the shared variation name and the saved pages/posts that reference the same `wp_block` ID.
- When editing a synced pattern directly (`wp_block` entity / “Edit pattern” mode), the section is still identified as shared rather than incorrectly reported as local.
- Detaching or switching a variation acts on the `core/block` controller on the page. When editing the pattern entity directly, the editor explains that the user must exit pattern editing first.

The database ID remains an implementation detail. Editors work with human-readable shared section names.
