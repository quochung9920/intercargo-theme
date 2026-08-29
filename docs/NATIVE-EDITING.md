# Native editing contract

The approved static HTML is the visual authority. Gutenberg changes how content is edited, not the frontend design.

## Editor rule

Visible content is edited on the canvas. The Inspector sidebar is configuration-only.

| Section | Visible content editing | Configuration |
|---|---|---|
| Hero | Core Heading, Paragraph, Buttons and Image blocks | media toolbar / form component |
| Services | Core content inside Service Card child blocks | card destination / media toolbar |
| Reasons | RichText directly on the section canvas | anchor/background only |
| Statement | RichText directly on the band; image via Replace toolbar | anchor/background only |
| Process | RichText directly on the section canvas | anchor/background only |
| Locations | Core blocks and Location child blocks | native block controls |
| Guide | Core blocks and list items | form provider/form selection |
| FAQ | Core Accordion blocks | native accordion controls |
| Enquiry | RichText directly on the section canvas | form provider/form, anchor/background |

Historical block IDs beginning with `acf/intercargo-` can remain in saved page content for compatibility. In V4 they are hidden compatibility aliases only. No ACF field groups or ACF block renderers are registered by the theme.

## Parent section form controls

A section that contains `intercargo/form` may declare `formControl` in its `template.json`. The generic package editor will expose Provider, Form and Accessible label on the parent section sidebar while the form block remains independently selectable. This keeps form selection native and package-local without hard-coding section names in the runtime.

## Safe links inside the editor

All frontend anchors are inert while rendered inside the Gutenberg content canvas. Clicking, keyboard-activating or middle-clicking a link may select/edit its block, but must never navigate away from the current post editor. Link URLs remain editable through native block controls. This protection is editor-only; frontend links are unchanged.
