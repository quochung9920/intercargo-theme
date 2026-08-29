# Page Settings Foundation (4.7.0.1 hotfix)

Page-shell choices are page metadata, not Gutenberg section content.

## Current controls

- **Transparent header** — OFF by default. Pages use the white/light header unless explicitly enabled.
- **Hide announcement / world clocks** — OFF by default. The 4.7.2 live world-clock announcement is therefore visible by default.

The existing static front page is migrated once to preserve its approved shell:

- Transparent header = ON
- Hide announcement = ON

## Data ownership

The settings are stored on the page as protected post meta:

- `_intercargo_transparent_header`
- `_intercargo_hide_announcement`

They must not be stored inside Hero, SecNav or any other shared section, because the same shared section may appear on pages with different shell settings.


## Page templates

### Section Builder

Use **Section Builder** for pages composed from full-width Gutenberg sections such as About Us, service pages and landing pages. It intentionally outputs only the rendered Gutenberg content inside `<main>`:

- no automatic page title
- no outer `.container`
- no outer `.section-pad`
- no `<article>` wrapper

Each section package owns its own container, spacing, background and responsive behaviour.

### Default template

The existing Default template remains unchanged for standard content pages that need the automatic page title and readable content container, such as Privacy Policy or Terms pages.
