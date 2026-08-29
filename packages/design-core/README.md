# Design Core

This package is the portable design-system core.

It must remain independent from any specific website, brand, CMS, or delivery platform. In particular, production code in this package must not depend on WordPress APIs or project-specific names and content.

The core owns reusable contracts and build-time behavior for concepts such as tokens, primitives, components, sections, variants, patterns, schema versioning, validation, compilation and export.

## Boundary

Allowed here:

- generic schemas and contracts;
- generic design primitives;
- generic validators and compiler utilities;
- schema/version migration infrastructure;
- project-independent tests.

Not allowed here:

- brand colors, copy, logos or imagery;
- freight/service-specific components;
- WordPress functions or Gutenberg registration;
- project page templates.

The Design Core is a build-time dependency. A released theme must continue to work when this directory is absent.
