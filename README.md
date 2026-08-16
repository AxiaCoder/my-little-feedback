# My Little Feedback

> Self-hosted user feedback, contact messages and public roadmap for your own products — with an MCP server so an AI assistant can read what your users actually said.

**Status: early development.** Nothing is deployed yet. See [Roadmap](#roadmap).

---

## What it is

A standalone application you host yourself, plugged into one or several of your products. It collects what users tell you, gives you one place to read and triage it, publishes a roadmap they can vote on, and exposes all of it to an AI assistant through the Model Context Protocol.

- **Feedback collection** — an embeddable widget, drop one `<script>` tag into any page
- **Contact messages** — public form with spam filtering
- **Public roadmap** — publish what you're working on, let users vote and comment
- **Back-office** — read, triage, moderate, reply
- **MCP server** — expose feedback to Claude and other MCP clients, in read and search

Multi-product from day one: one instance, several products.

### What it deliberately does not do

**It does not create tickets in your issue tracker.** Turning raw feedback into a well-scoped ticket is a judgement call, and it belongs wherever you already make that judgement. This project's job stops at surfacing feedback cleanly — through the API and through MCP — so that whatever you use downstream can decide. Building a second, dumber path into your tracker would only produce noise.

---

## Architecture

Four components, four languages, one repository. Each folder is self-contained and owns its own dependencies.

```
                  ┌────────────────┐
   your product ─▶│    widget      │  TypeScript · Web Component
                  │  (browser)     │
                  └───────┬────────┘
                          │ HTTPS
                  ┌───────▼────────┐
                  │    ingest      │  Go · the only public write surface
                  │ validate, rate │  rate limiting, spam filtering
                  │ limit, filter  │
                  └───────┬────────┘
                          │ internal HTTP
                  ┌───────▼────────┐        ┌──────────────┐
                  │     core       │◀───────│     mcp      │  Python
                  │ API · admin ·  │  HTTP  │ MCP server   │
                  │ roadmap        │        └──────────────┘
                  │ PHP · Symfony  │
                  └───────┬────────┘
                          │
                    ┌─────▼─────┐
                    │ PostgreSQL │
                    └───────────┘
```

**One component owns the database: `core`.** `ingest` never writes to it directly — it validates, throttles and forwards to the internal API. `mcp` never reads it directly either. A single authority over the schema is what keeps four languages from drifting apart.

**The API contract lives in `contracts/openapi.yaml`**, generated from `core` and committed. CI regenerates it and fails on any difference. In a polyglot repository nothing else catches a breaking change — there is no shared compiler.

Why each language was chosen, and why these boundaries: [`docs/architecture.md`](docs/architecture.md).

---

## Repository layout

| Path | What it is | Language |
|---|---|---|
| `core/` | API, back-office, roadmap, moderation, auth | PHP 8.3 · Symfony 7.4 |
| `ingest/` | Public write endpoint: validation, rate limiting, spam | Go |
| `mcp/` | MCP server exposing feedback to AI clients | Python |
| `widget/` | Embeddable collection widget | TypeScript · Web Component |
| `contracts/` | Generated OpenAPI specification | — |
| `docs/` | Architecture and decisions | — |

Folders are named after their **role, not their language** — so the name stays true if the implementation changes.

There is deliberately **no unified tooling**: no root build script, no global linter, no monolithic CI. Each component is an island with its own package manager, and CI jobs are filtered by path so a change to the widget doesn't rebuild everything else.

---

## Requirements

| Tool | Version | Needed for |
|---|---|---|
| Docker + Compose | any recent | running the stack |
| PHP | 8.3+ | `core/`, if working outside Docker |
| Composer | 2.x | `core/` dependencies |
| Go | 1.22+ | `ingest/` — not needed yet |
| Python | 3.11+ | `mcp/` — not needed yet |
| Node | 20+ | `widget/` — not needed yet |

Only PHP and Composer matter today: `ingest`, `mcp` and `widget` are not implemented yet.

---

## Running locally

```bash
docker compose up -d
docker compose exec core php bin/console doctrine:migrations:migrate
```

The API is then on http://localhost:8130, and the generated OpenAPI documentation on http://localhost:8130/api/doc.

No `.env.local` is needed for this: Compose passes `DATABASE_URL` as a real environment variable, and Symfony gives those precedence over any `.env*` file. Create one only to override something for yourself — it is git-ignored.

**Running `core` outside a container** is also supported: Postgres is published on `localhost:5440`, which is what `core/.env` already points at, so `php -S localhost:8130 -t core/public` works against the Compose database with no extra configuration.

---

## Roadmap

Milestones are built strictly in this order — each one has to run before the next starts.

- [ ] **1 · Core** — feedback can be created through the API, attached to a product, read in the back-office
- [ ] **2 · Widget** — embeddable Web Component, isolated in a Shadow DOM
- [ ] **3 · MCP server** — read and search over feedback, Streamable HTTP transport
- [ ] **4 · Ingest** — public endpoint moved to Go, with rate limiting and spam filtering
- [ ] **5 · Public roadmap** — publication, voting, comments, moderation

---

## License

MIT — see [LICENSE](LICENSE).
