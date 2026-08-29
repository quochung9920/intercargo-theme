# Intercargo Connect — 4.12.0 Service CTA Band

This release adds the next Air Freight service-page section: **Service CTA Band**, based on the supplied “Not sure this is the right fit?” source composition.

## What changed

- Added reusable `intercargo/service-cta` as **Service CTA Band**.
- Uses the global `.container`, theme typography/colour tokens and the existing provider-neutral Form component.
- Matches the Air Freight source band geometry: dark band, responsive heading/copy, compact inline form from 960px, and exact 1440px vertical/title anchors.
- Does not create a second form system and does not appear in dynamic Service Navigation by default.
- Theme folder remains `intercargo-vite/` for in-place WordPress replacement.
