# WordPress adapter

This package owns the WordPress/Gutenberg runtime used by generated themes.

`runtime/` is copied to the generated theme as `inc/`. It owns block discovery, composition validation, section rendering, synced sections, page settings, asset loading, editor integration, migrations and compatibility behavior.

The adapter is a build-time source dependency. Generated themes must never require files from this package at runtime. The existing `intercargo/*` namespace is intentionally preserved to protect saved Gutenberg content during this refactor.
