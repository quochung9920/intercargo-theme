# Portable Design Core

The Design Core is the reusable, framework-neutral contract layer of the workspace.

It may define schemas, design-package conventions, portable tokens/contracts and compiler-facing metadata, but it must not contain WordPress APIs, Intercargo branding, project sections, project assets or generated theme files.

Projects consume the contracts; platform adapters translate project packages to a target runtime. A project can therefore move to another repository or runtime without moving its generated WordPress theme.

## Hard boundary

Code in this package must not depend on `projects/`, `themes/` or WordPress runtime APIs. The architecture validator enforces this boundary.
