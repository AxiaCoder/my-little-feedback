# widget — TypeScript

**Not implemented yet. Milestone 2.**

## Role

The embeddable collection widget. One tag in any page:

```html
<script src="https://your-instance/widget.js" data-product="my-product" defer></script>
```

It renders a floating button, opens a panel with a form (feedback type, message, optional e-mail), attaches automatic context (current URL, viewport, user agent), submits, and confirms.

**It is a form with a wrapper, not an application.** Out of scope on purpose: screenshot capture, authentication, and displaying the roadmap.

## Why a Web Component and not a React package

The host products are React applications, so a React component looked obvious. It is not:

1. **Version coupling.** A React package is tied to each host's React version, and every fix requires redeploying every host. A `<script>` is deployed once, server-side.
2. **It would make publishing this pointless** — a widget that only embeds into React is of no use to anyone else.
3. **Weight** — bundling React to render a form costs ~45 kB and puts two React instances on one page.

## The Shadow DOM is the point

This runs inside a page it does not control. A global `button {}` rule, a Tailwind reset or a stray `input` selector is enough to disfigure it — and its own CSS can leak outward. A Shadow DOM makes that boundary airtight in **both** directions. Prefixing every class and hoping does not survive an aggressive reset.

**Fallback** if hand-written DOM becomes tedious: Preact bundled in (~3 kB) keeps the universal `<script>` and the Shadow DOM while restoring JSX.

## Budget

Under 15 kB minified and gzipped, zero runtime dependencies.
