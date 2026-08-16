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
| `core/` | PHP 8.3 · Symfony 7.4 · Doctrine ORM · Twig | API, back-office, roadmap, auth. **Owns the database** |
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
- **Nothing is committed on `main`.** Branch, open a pull request, let CI run, merge. A `pre-commit` hook refuses commits on `main`, and the pull request template asks for what the diff cannot say.
- **Conventional Commits, enforced.** `<type>(<optional scope>): <subject>`, subject in the imperative and under 72 characters, no trailing period, blank line before the body. Types: `build` `chore` `ci` `docs` `feat` `fix` `perf` `refactor` `revert` `style` `test`. A `commit-msg` hook checks every commit and a workflow checks every pull request title.
- **The hooks live in `.githooks/` and git does not install them on clone.** A SessionStart hook points `core.hooksPath` at them automatically; outside a Claude session, run `git config core.hooksPath .githooks` once. Until that is set the directory is inert and nothing stops a commit on `main`.
- No personal data, no private hostnames, no ticket keys, no real e-mail addresses anywhere in the repository — it is public.
- The public product identifier (`data-product="..."`) **is not a secret**. Never treat it as authentication; protection is server-side only.
- Doctrine migrations are committed, never edited after being applied.

---

## Current milestone

**1 — Core.** Done when a feedback item can be created through the API, attached to a product, and read in the back-office.

Specified in `docs/specs/01-core-data-model.md` — entities, both endpoints, error shapes
and seed strategy. Read it before writing any of the code below.

Remaining:
- [ ] Data model: `Product`, `FeedbackType`, `Feedback` (+ status, submitter context)
- [ ] Doctrine migration, seeding the default feedback types
- [ ] `POST /api/feedback` and `GET /api/feedback` with validation
- [ ] Back-office listing page (Twig)
- [x] OpenAPI generation working — `contracts/openapi.yaml` is generated and committed;
      the CI job that diffs it has still never executed
- [x] Working `docker compose up`

Not in this milestone: widget, MCP, Go service, public roadmap, authentication.

---

## Where we stopped — 2026-08-16, end of session

> ⚠️ **This section is temporary and does not belong in this file.** `CLAUDE.md` states how to
> work on the project; it is not a logbook, and on a public repository a running commentary on
> where a session stopped is noise for every reader who is not us.
>
> **First action of the next session: turn everything below into GitHub issues**, then delete
> this section. Tracked work belongs in the issue tracker, where it can be assigned, closed and
> referenced from a pull request — none of which a Markdown bullet can do.

**The baseline is real now.** Everything below was executed, not read: the stack was torn
down with `docker compose down -v` and rebuilt from scratch, and the CI ran twice on GitHub.

| | |
|---|---|
| Repository | `github.com/AxiaCoder/my-little-feedback`, public, remote `origin` over SSH |
| `main` | green — `e5095b4`, PR #1 merged |
| Verified locally | stack up, migrations, PHPUnit, PHPStan, PHP-CS-Fixer, `composer openapi`, `/api/doc` and `/api/doc.json` |
| Verified on CI | all four steps, **including the OpenAPI contract check**, on both the `push` and `pull_request` triggers |

**Still true, still nothing written:** no entity, no controller, no migration, no template.
The application does nothing yet — what exists is a scaffold that has been proven to run.

### Next session, in this order

0. **Open a GitHub issue per item below, then delete this whole section.** The list stops living
   in the repository the moment it can live in the tracker.
1. **Test database plumbing.** `mlf_test` does not exist and nothing creates it. The single
   test only boots the kernel, so this is invisible today and blocking at the first
   functional test. Spec §2.7 requires the suite to run the **migrations** rather than
   `doctrine:schema:create`, so that the seeded feedback types exist and the migrations get
   exercised on every run. Roughly thirty lines, no unknowns.
2. **Entities** — `Product`, `FeedbackType`, `Feedback`, the `SubmitterContext` embeddable,
   the `FeedbackStatus` enum, repositories.
3. **The migration**, seeding the three default feedback types in the same file that creates
   their table.
4. `POST /api/feedback`, then `GET /api/feedback` — one pull request each.
5. Twig back-office listing, then regenerate and commit `contracts/openapi.yaml`.

Steps 2 and 3 travel together in one pull request. Read
`docs/specs/01-core-data-model.md` before starting any of them — it is accepted, not a draft.

### Open, decided against for now

**Branch protection on `main` is not enabled.** The local hook catches the honest mistake but
is bypassed by `--no-verify` and absent from a fresh clone. Only a GitHub ruleset actually
prevents a direct push. Worth enabling; not done because it changes repository settings and
that call is the owner's.

---

## Known issues / decisions pending

- **Two authentication populations** — administrators and end users who vote on the roadmap. The model is not designed yet. It becomes blocking at milestone 5, not before.
- **The public roadmap is the heaviest part of the scope**, heavier than the MCP server: public identity, open sign-up, moderation, anti-spam. Not estimated.
- **The CI workflow has never executed** — there is no remote yet. Treat `.github/workflows/core.yml` as untested code. The commands it runs have all been verified by hand inside the container, but the runner path (no container, `../contracts` relative to `core/`) has not.
- **`nelmio/api-doc-bundle` is wired by hand** — `bundles.php`, `config/packages/nelmio_api_doc.yaml` and `config/routes/nelmio_api_doc.yaml` are written, not generated. Its recipe lives in `recipes-contrib`, which `composer.json` disables with `allow-contrib: false`. Do not expect `composer recipes:install` to fix anything there. It also needs `symfony/asset`: without it the bundle silently drops the Swagger UI controller and the route 500s on a controller that "does not exist".
- **`core/router.php` is not optional.** PHP's built-in server 404s on any URI whose last segment contains a dot, before Symfony ever sees it — `/api/doc.json` included. The router script is what routes those to the front controller.
- On the author's Windows machine, `php.ini` and Composer live inside PHP's WinGet package directory. A package update can wipe them; if PHP suddenly reports missing extensions, that is why.
- **`phpstan.neon` points at the _test_ container, not the dev one.** `tests/` is in the analysed paths and functional tests resolve services through the test container, which exposes private services the dev container hides — pointing at dev makes PHPStan claim that services the tests genuinely resolve do not exist. That container XML has to exist before the analysis runs, so `composer stan` warms the test cache first. **Call `composer stan`, never `vendor/bin/phpstan` directly** — the bare binary fails on a clean checkout with an error that names a cache file rather than the cause.

---

## Session pre-check

`.claude/settings.json` registers two **SessionStart hooks**:

- `check-container-engine.sh` — verifies that a container engine is installed **and responding**, and says so loudly if it is not.
- `check-git-hooks.sh` — points `core.hooksPath` at `.githooks/`, because git leaves versioned hooks inert until someone does. It configures rather than reports: a hook nobody installed protects nothing.

This is a hook rather than a line of documentation on purpose: an instruction in a file can be skipped, a hook cannot. Every useful command here goes through Compose — the database, the migrations, the OpenAPI dump — so a session started against a dead engine wastes several commands before the cause becomes obvious.

**If the pre-check fails, stop and fix that first.** Do not propose `docker` or `compose` commands until the engine responds; they cannot succeed. To review or disable the hook: `/hooks`.

---

## How to run locally

```bash
docker compose up -d --build
docker compose exec core php bin/console doctrine:migrations:migrate
```

API on http://localhost:8130, Swagger UI on http://localhost:8130/api/doc, raw
specification on http://localhost:8130/api/doc.json.

Everything below runs **inside the container** — the host is not expected to have PHP,
and `phpstan.neon` and the OpenAPI dump both resolve paths as they exist in the image:

```bash
docker compose exec core composer test      # PHPUnit
docker compose exec core composer stan      # PHPStan (warms the test container first)
docker compose exec core composer cs        # PHP-CS-Fixer (dry run)
docker compose exec core composer cs:fix    # PHP-CS-Fixer (apply)
docker compose exec core composer openapi   # regenerate contracts/openapi.yaml
```

**Do not set `APP_ENV` in `docker-compose.yml`.** `core/.env` already declares it, and a
real environment variable outranks PHPUnit's `force="true"` — the symptom is a test suite
that silently runs against the dev environment and fails on the first environment assertion.
