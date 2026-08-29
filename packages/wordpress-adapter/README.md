# WordPress Adapter

This package is the CMS adapter between the portable Design Core/project model and WordPress/Gutenberg.

Unlike `packages/design-core`, this package is allowed to understand WordPress concepts such as block registration, Gutenberg editor integration, `wp_block` synced patterns, REST routes and WordPress asset loading.

It must not contain project-specific branding, copy or section styling.

During migration, the existing runtime remains in `/inc`. Later phases will move generic WordPress runtime behavior here and the release compiler will snapshot the required adapter runtime into the standalone theme output.

A production theme must never `require`, `include`, `import` or otherwise read this package by a relative runtime path. The adapter is copied/compiled into the theme at build time.
