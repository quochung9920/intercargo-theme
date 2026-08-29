# Final QA Notes — 4.5.0

Automated release checks cover PHP/JSON/JS syntax, section/component discovery, parent/child block contracts, legacy ID migration, editor hydration safety and visual/source fingerprints.

Safe launch-readiness changes included in this release:

- Internal launch placeholder contact/legal copy is no longer emitted by default.
- Historical placeholder values are suppressed if still present in old section/theme data.
- Privacy is a real WordPress Privacy Policy link at all viewport sizes when configured.
- Muted text tokens were darkened to meet WCAG AA against the light theme surfaces used by the site.
- Hero editor geometry and image layering fixes remain intact.
- Favicon fallback, FAQ icon fixes, form-state normalization, provider-neutral form controls and editor-safe links remain intact.

Items that depend on WordPress content/settings rather than theme source still require staging review:

- Navigation menu destinations configured in wp-admin.
- Final phone/email/ABN/licence content supplied by the business.
- Actual Contact Form 7 / Gravity Forms mail delivery and CRM integration.
- Privacy page content itself.
- SEO/sitemap behavior controlled by WordPress/plugins/hosting.

The automated environment used to package this release cannot resolve the private SiteGround staging hostname, so no claim is made that an authenticated/live browser run was performed from the build environment.


## 4.5.1 button surface fix

Gutenberg `core/buttons` wrappers are layout-only. Variant wrapper classes such as `button--dark` and `button--yellow` must not paint a background. The clickable `.wp-block-button__link` is the single visual surface and owns its background, border radius, shadow, focus state and hover motion.
