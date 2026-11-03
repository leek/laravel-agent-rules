# CSS

**Purpose:** the application's stylesheets and their build entry points.

> Refines `app/CLAUDE.md` for this directory.

## Selector performance

- **MUST NOT** key a `:has()` or `:not()` argument on the `style` attribute (`.card:has(> .panel[style*="display: none"])`). Blink then re-styles the ancestor chain of **every** element whose inline style changes, so each Alpine `x-show`, transition or tooltip can cost tens of milliseconds on a large page. Match state through a class or a `data-*` attribute instead.
- Diagnose a suspected style-keyed invalidation by writing `el.style.color` on a bare element and timing `getComputedStyle()` on another; more than about 1 ms means one exists.
