# Section Reuse and Synchronization

Intercargo separates **design identity**, **local content**, and **shared content identity**.

## Local is the default

A section inserted from the section library is always local. It has no shared ID and its content lives only in that page. Merely using `intercargo/services` on two pages never connects them.

## Save a local section as shared

Select the outer section and open **Reuse & sync** in the block sidebar. Enter a business-readable name such as `Services — Main` and choose **Save as shared**.

The theme serializes the current section exactly as it exists, creates a WordPress Core `wp_block` synced-pattern record, and replaces only that page instance with a `core/block` reference. The numeric database ID remains an implementation detail and is never the editor-facing identity.

## Reuse an existing variation

A local section can choose **Use existing shared section** and select another saved variation of the same section type. For example:

```text
Services template
├── Services — Main       -> Home, About
├── Services — Contact    -> Contact, Quote
└── Local Services        -> Landing page only
```

Only pages referencing the same `wp_block` record synchronize.

## Shared section controls

When a `core/block` reference belongs to an Intercargo shared section, **Reuse & sync** shows its human-readable name. Editors can:

- switch to another shared variation of the same section type;
- detach the saved content back to a local `intercargo/*` section.

Detach parses the content stored in the shared `wp_block`; it never recreates the section from `template.json`, so saved content is preserved.

## WordPress Core remains the synchronization engine

The theme adds section-aware UX and metadata only. WordPress Core owns synced-pattern storage, revisions, rendering and references. Shared records use `post_type = wp_block`.

Two metadata values are stored on each shared record:

- `_intercargo_section_type`: e.g. `services`;
- `_intercargo_shared_key`: stable UUID for future migrations/automation.

The WordPress numeric post ID is internal and may change when a site is migrated.

## Unlimited section instances

Content section types are not page-level singletons. Hero, Services, FAQ, Guide, Locations, Enquiry and other content sections can be inserted multiple times. Infrastructure sections may explicitly opt out of synchronization when their output is derived from the current page. `intercargo/service-navigation` is the canonical example: its tabs come from sibling sections, so saving it as a synced pattern would be stale by design.

Repeatability and synchronization are independent:

- two local Hero instances are two independent local sections;
- `Hero — Homepage` and `Hero — Campaign` may be two different shared variations;
- the same shared Hero variation may be referenced on many pages;
- adding another Hero does not automatically connect it to any existing Hero.

For backward compatibility, the first rendered instance keeps its historical anchor such as `#home`, `#services` or `#faq`. Additional instances receive deterministic runtime suffixes such as `#home-2` and `#services-2`, so the page never contains duplicate HTML IDs.

