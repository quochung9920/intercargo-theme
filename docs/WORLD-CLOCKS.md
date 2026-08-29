# World clocks / announcement

The global announcement bar renders above the site header by default.

Default clocks:
- Australia — `Australia/Sydney`
- China — `Asia/Shanghai`
- US — `America/Los_Angeles`
- Singapore — `Asia/Singapore`
- Japan — `Asia/Tokyo`
- South Korea — `Asia/Seoul`

PHP renders an initial local time so the bar never starts empty. `view.js` refreshes the clocks in the browser with `Intl.DateTimeFormat` every 30 seconds, including daylight-saving changes handled by the browser timezone database.

Per-page visibility is controlled by **Intercargo Page Settings → Hide announcement / world clocks**. The existing front page remains hidden through the 4.7.0 compatibility migration.

Developers can change the clock list without touching the renderer through the `intercargo_world_clock_items` filter.
