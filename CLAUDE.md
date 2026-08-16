# My Little Feedback — Claude Context

> Last updated: 2026-08-16

---

## Project

- **Name:** My Little Feedback (MLF)
- **Goal:** Self-hosted user feedback, contact messages and public roadmap for the owner's own products (My Little Library, My Little Trivia), exposed to AI assistants through an MCP server.
- **Status:** Early development — milestone 1 (core) not finished.
- **Repo:** `github.com/AxiaCoder/my-little-feedback` — **public**
- **Deployed on:** nothing yet. Target is the main Hostinger VPS (Easypanel), on a subdomain of an existing domain.

---

## Ground rules

**Everything in this repository is written in English** — code, comments, documentation, commit messages, branch names, pull requests. No exception. The repository is public from the first commit, so there is no translation pass to plan for later.

**This project is a learning vehicle, not a delivery race.** That inverts the usual criterion: a choice that ships faster but teaches nothing is a bad choice here. Do not propose a library or a framework whose selling point is that it saves time — propose the one that makes the mechanism visible. This is why API Platform was rejected in favour of hand-written controllers, and why the widget is a Web Component rather than a React package.

**Build order is enforced and not negotiable:** core → widget → mcp → ingest → public roadmap. A language the author is learning never sits on the critical path.

---

## Stack

| Component | Tech | Notes |
|---|---|---|
| `core/` | PHP 8.3 · Symfony 7.4 · Doctrine ORM | API, back-office, roadmap, auth. **Owns the database** |
| `ingest/` | Go | Public write endpoint. Not implemented yet |
| `mcp/` | Python · official MCP SDK | Streamable HTTP transport, **not SSE** (deprecated). Not implemented yet |
| `widget/` | TypeScript · Web Component · Shadow DOM | No framework. Not implemented yet |
| Database | PostgreSQL 16 | |
| Runtime | Docker + Docker Compose | |

**Quality tooling in `core/`:** PHPUnit, PHPStan (with `phpstan-symfony`), PHP-CS-Fixer.

**Deviations worth knowing:**
- No API Platform — hand-written controllers plus `nelmio/api-doc-bundle` for the OpenAPI output.
- No unified tooling across components. No root build script, no global linter, no monolithic CI. Each folder is an island with its own package manager; CI jobs are filtered by path.

---

## Architecture

```
widget (browser) ──HTTPS──▶ ingest (Go) ──internal HTTP──▶ core (Symfony) ──▶ PostgreSQL
                                                              ▲
                                                mcp (Python) ─┘
```

**Only `core` talks to the database.** `ingest` validates, rate-limits and forwards; `mcp` calls the API. A single authority over the schema is what stops four languages from drifting apart.

**`contracts/openapi.yaml` is generated from `core` and committed.** CI regenerates it and fails on any difference — that check is what replaces the compiler a polyglot repository does not have. If you change an API response shape, regenerate it in the same commit.

Full reasoning, including the alternatives that were rejected: `docs/architecture.md`.

---

## Dev conventions

- All content in English (see Ground rules).
- Conventional commit prefixes (`feat:`, `fix:`, `docs:`, `chore:`).
- No personal data, no private hostnames, no ticket keys, no real e-mail addresses anywhere in the repository — it is public.
- The public product identifier (`data-product="..."`) **is not a secret**. Never treat it as authentication; protection is server-side only.
- Doctrine migrations are committed, never edited after being applied.

---

## Current milestone

**1 — Core.** Done when a feedback item can be created through the API, attached to a product, and read in the back-office.

Remaining:
- [ ] Data model: `Product`, `Feedback` (+ type, status, submitter context)
- [ ] Doctrine migration
- [ ] `POST /api/feedback` and `GET /api/feedback` with validation
- [ ] Back-office listing page (Twig)
- [ ] OpenAPI generation wired into CI
- [ ] Working `docker compose up`

Not in this milestone: widget, MCP, Go service, public roadmap, authentication.

---

## Known issues / decisions pending

- **Two authentication populations** — administrators and end users who vote on the roadmap. The model is not designed yet. It becomes blocking at milestone 5, not before.
- **The public roadmap is the heaviest part of the scope**, heavier than the MCP server: public identity, open sign-up, moderation, anti-spam. Not estimated.
- **No container engine yet** — `docker compose up` has never been run and is unverified. Same for the CI workflow: it has never executed. Treat both as untested code, not as a working baseline.
- On the author's Windows machine, `php.ini` and Composer live inside PHP's WinGet package directory. A package update can wipe them; if PHP suddenly reports missing extensions, that is why.
- **`phpstan.neon` points at the _test_ container, not the dev one.** `tests/` is in the analysed paths and functional tests resolve services through the test container, which exposes private services the dev container hides — pointing at dev makes PHPStan claim that services the tests genuinely resolve do not exist. The file is regenerated by running the test suite once, so **run the tests before the analysis** on a clean checkout.

---

## How to run locally

```bash
cp core/.env core/.env.local
docker compose up -d
docker compose exec core php bin/console doctrine:migrations:migrate
```

API on http://localhost:8130, generated OpenAPI docs on http://localhost:8130/api/doc.

```bash
# Inside core/
composer test          # PHPUnit
composer stan          # PHPStan
composer cs            # PHP-CS-Fixer (dry run)
composer cs:fix        # PHP-CS-Fixer (apply)
```
