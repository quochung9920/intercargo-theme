# Standalone Intercargo Theme Output

This directory is reserved for the deployable WordPress theme produced by the architecture/export pipeline.

The final output must be fully self-contained. It may not access `packages/` or `projects/` at runtime and must continue to work when those source directories are removed from the deployment artifact.

During migration phase 1 this directory intentionally contains no generated runtime files. The existing root theme remains the runnable baseline while the separation boundary is introduced without visual changes.

When generation is enabled, generated files in this directory must not be edited manually; changes belong in the portable packages or the Intercargo project source and are then rebuilt.
