# Intercargo Design Project

This directory will become the source of truth for Intercargo-specific design data and implementation.

Target contents include brand tokens, global presentation, components, sections, patterns, shell markup and project assets. None of those belong in the portable Design Core.

## Migration phase 1

The existing `/design` directory remains the visual authority so this architectural change produces zero frontend differences. Files will move into this project in later phases only after the compiler/adapter boundaries are in place and regression tests protect the current output.

Do not duplicate or rewrite current section implementations here during phase 1.
