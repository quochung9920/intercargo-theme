# UI Components and Motion

The theme uses reusable UI primitives instead of section-specific hover code.

## Component folders

- `design/components/button/` — button variants and press/hover states
- `design/components/link/` — text/arrow link micro-interactions
- `design/components/card/` — interactive card behavior
- `design/components/accordion/` — accordion hover/open-state presentation
- `design/components/motion/` — GSAP reveal/stagger runtime

Component folders use `component.json` and are auto-discovered by `inc/ui-components.php`.
Normal section work must not register component assets manually.

## Core block styles

Buttons expose: Dark, Yellow, Ghost and Text / Arrow.
Core Group exposes: Interactive Card.

## Future section contract

A section can use generic classes such as:

- `.ic-card`
- `.ic-button`
- `.ic-link`
- `.ic-motion-reveal`

Existing Intercargo selectors are mapped to the same primitives so current saved content needs no migration.

## Vendor libraries

Pinned libraries are registered by `inc/vendor-libraries.php` and load only when requested.
A block package can request them in its local `block.json`:

```json
"intercargo": {
  "libraries": ["swiper", "gsap", "scrolltrigger"]
}
```

Available keys: `gsap`, `scrolltrigger`, `flip`, `splittext`, `scrollto`, `motionpath`, `observer`, `draggable`, `swiper`, `lenis`, `lottie`.

Do not enable Lenis globally by default. Smooth scrolling changes native scroll behavior and should be an explicit design decision per site.

## Motion rules

- CSS handles hover/press micro-interactions.
- GSAP handles reveal, stagger and timeline motion.
- `prefers-reduced-motion: reduce` disables the GSAP motion runtime.
- Gutenberg editor does not load the frontend motion runtime.
