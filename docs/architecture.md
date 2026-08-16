# Architecture decisions

Why this project is built the way it is. Every entry here is a choice that had a credible alternative — decisions with no alternative are not worth recording.

---

## Why four languages

This project is a learning vehicle as much as a tool. That inverts the usual criterion: **a choice that ships faster but teaches nothing is a bad choice here.**

That only works if the boundaries are real. A language picked for the sake of touching it, dropped in an arbitrary place, produces four half-finished chantiers that block each other. So each component had to justify its boundary on its own terms:

| Component | Language | The boundary that justifies it |
|---|---|---|
| `core` | PHP · Symfony | CRUD with authentication, sessions and an admin surface — Symfony's home ground. It is also ~80% of the work, so it is where the learning actually happens |
| `ingest` | Go | The only surface exposed to the internet **in write mode**. Isolating it has security value on its own. Small, functionally trivial, and — critically — **if it stalls, everything else still runs** |
| `mcp` | Python | An MCP server is naturally a separate process that talks HTTP to the core. Python is the language of the official MCP course material |
| `widget` | TypeScript | It runs in a browser. Not a choice |

**Build order is enforced: core, then mcp, then ingest.** Three simultaneous learning curves is how side projects die. A language you are learning never sits on the critical path.

---

## Why the database has a single owner

`core` is the only component that talks to PostgreSQL. `ingest` validates, throttles, filters and forwards to the internal API. `mcp` calls the API too.

The alternative — letting `ingest` write directly — means the Doctrine schema becomes an implicit contract between two codebases in two languages, and a migration silently breaks the other one. A queue between the two would decouple them properly, but at this scale it adds infrastructure to maintain and solves a load problem that does not exist.

---

## Why OpenAPI is generated and checked in CI

**In a polyglot repository there is no compiler to catch you.** Change the shape of a feedback object in a TypeScript monorepo and every wrong call fails to build. Here the same structure is described in PHP, in Go and in Python, and nothing reports that they have drifted — you find out in production.

So `contracts/openapi.yaml` is generated from `core`, which owns the model, and committed. CI regenerates it and fails on any difference. That check is what replaces the compiler; without it, this layout is a trap rather than a structure.

---

## Why hand-written controllers instead of API Platform

API Platform would generate the REST API and its OpenAPI specification almost for free. It was rejected on the project's own criterion: it hides most of what happens between an HTTP request and a database row, and understanding that is the point of writing the core in Symfony.

`nelmio/api-doc-bundle` gives the generated specification without the magic.

**Revisit this if** the roadmap and voting endpoints turn into a large amount of repetitive CRUD — the trade-off would then be worth re-examining.

---

## Why the widget is a Web Component, not a React package

The products this plugs into are React applications, so a React component looked like the obvious answer. Three reasons it is not:

1. **Version coupling, and the cost of every fix.** A React package has to be installed and mounted in each host's tree, which ties the widget to their React version and means **every correction requires redeploying every host**. A `<script>` tag is deployed once, server-side, and all hosts get it.
2. **It would make publishing this repository pointless.** A feedback widget that only embeds into React is of no use to anyone else — and open-sourcing it is precisely the argument for building it this way.
3. **Weight** — bundling React to render a form costs ~45 kB and puts two React instances on the same page.

**The decisive constraint is style isolation.** The widget is injected into a page it does not control: a global `button {}` rule, a Tailwind reset or a stray `input` selector is enough to disfigure it, and its own CSS can leak outward. A Shadow DOM makes that boundary airtight in both directions. Prefixing every class and hoping does not survive an aggressive reset.

**Fallback if hand-written DOM becomes tedious:** Preact bundled in (~3 kB) keeps the universal `<script>` and the Shadow DOM while restoring JSX.

---

## Why the public identifier is not a secret

The widget is public, so `data-product="..."` is an identifier, not a credential. Anyone can read the script and post to the endpoint.

All protection is therefore server-side — allowed origins, rate limiting, spam filtering — which is exactly what justifies `ingest` being a separate service rather than one more route in Symfony.

---

## Why no SaaS

The market is saturated (Canny, Featurebase, Productboard, UserVoice, Nolt, Upvoty), its price floor is zero thanks to existing open-source tools (Fider, Astuto, Logchimp), the AI-feedback layer is already busy (Enterpret, Cycle, BuildBetter, Pylon, Released), and Atlassian ships Jira Product Discovery natively.

The obstacle is not the product, it is distribution. Building for SaaS would force multi-tenancy, billing, support and third-party data compliance from day one, in exchange for improbable revenue.

**Self-hosted and open source instead** — which is also what makes the widget's universal `<script>` worth the extra effort.
