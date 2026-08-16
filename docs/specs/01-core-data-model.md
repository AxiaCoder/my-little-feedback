# Spec 01 — Core data model and feedback API

> Milestone 1. Status: **accepted.** Revised once after review — feedback types moved from a PHP
> enum to a reference table (§2.4), which is why §2.7 exists.
> Scope: `Product`, `FeedbackType`, `Feedback`, `POST /api/feedback`, `GET /api/feedback`,
> back-office listing.
> Out of scope: authentication, voting, the public roadmap, the widget, `ingest`, `mcp`.

---

## 1. What this milestone has to prove

A feedback item can be created through the API, attached to a product, and read back in the
back-office. Everything below exists to serve that sentence and nothing wider.

The two endpoints specified here are **not the public write path**. `ingest` (Go, milestone 4)
will be the only surface exposed to the internet in write mode; it validates, rate-limits and
forwards to `POST /api/feedback` over the internal network. Until then these routes are
unauthenticated and bound to localhost through Compose. That is acceptable for milestone 1 and
becomes a defect the moment anything is deployed — noted again in §7.

---

## 2. Entities

### 2.1 Identifiers

**Primary keys are UUID v7**, stored in the native PostgreSQL `uuid` column type through
`symfony/uid` (already a dependency).

The alternative was a `BIGINT` identity column, which the Doctrine configuration currently
prefers. It was rejected because these identifiers end up in public URLs on the roadmap:
sequential integers advertise how much feedback the products actually receive, and let anyone
walk the whole table by counting. UUID v7 keeps the time-ordering that makes an index behave,
without publishing the volume.

Cost accepted: 16 bytes per key instead of 8, and identifiers that are unpleasant to type by
hand in `psql`. At this scale neither matters.

### 2.2 `Product`

The product a feedback item belongs to — My Little Library, My Little Trivia, and whatever
comes next.

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `slug` | `string(60)` | not null, **unique**, `[a-z0-9-]+` | the public `data-product="…"` value |
| `name` | `string(120)` | not null | human label, shown in the back-office |
| `createdAt` | `timestamptz` | not null, immutable | |

`slug` is the public identifier. It is **not a credential** — anyone reading the widget's
`<script>` tag can see it and post to the endpoint. All protection is server-side, which is
exactly what justifies `ingest` existing at all.

There is no creation endpoint for products in this milestone. They are inserted by a Doctrine
fixture or by hand; a back-office CRUD comes later.

### 2.3 `Feedback`

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `product` | `ManyToOne(Product)` | not null, `ON DELETE RESTRICT` | see below |
| `type` | `ManyToOne(FeedbackType)` | not null, `ON DELETE RESTRICT` | reference table, §2.4 |
| `status` | `string(20)` | not null, default `new` | PHP backed enum, §2.5 |
| `title` | `string(160)` | **nullable** | |
| `message` | `text` | not null, 10–5000 chars | |
| `submitter` | embeddable | nullable fields | §2.6 |
| `createdAt` | `timestamptz` | not null, immutable | |
| `updatedAt` | `timestamptz` | nullable | set when status or content changes |

**`title` is nullable on purpose.** The widget's job is to make reporting cheap; a mandatory
subject line is the field people abandon a form on. The back-office falls back to the first
line of `message` when the title is absent.

**`ON DELETE RESTRICT` on the product**, not a cascade. Deleting a product that still holds
feedback should fail loudly rather than silently destroy the history — and product deletion is
not even implemented yet, so the safe direction costs nothing.

Indexes: `(product_id, created_at DESC)` for the back-office listing, `(status)` for the
roadmap queries that arrive at milestone 5.

### 2.4 `FeedbackType` — what the submitter is telling us

**A table, not an enum.** This is self-hosted software: whoever installs it should be able to
decide that they want `translation` and `accessibility` instead of the three defaults, without
patching PHP and without a migration of their own.

| Field | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `slug` | `string(30)` | not null, **unique**, `[a-z0-9-]+` | the stable value in the API payload |
| `label` | `string(60)` | not null | what the widget displays |
| `position` | `smallint` | not null, default `0` | ordering in the widget, then by `label` |
| `isActive` | `boolean` | not null, default `true` | see below |

Deletion is `ON DELETE RESTRICT` from `Feedback`, so a type that has ever been used can never be
removed. `isActive` is the way out: an inactive type disappears from the widget and from the
creation endpoint, while the historical feedback that carries it stays readable. Without that
column, configurability is half-built — you can add a type you want but never retire one you
regret.

**Why `type` is data and `status` is not** — the test is whether code branches on the value.
Nothing in this application does anything different for a `bug` than for an `idea`: it is a
label, an icon and a filter facet. Statuses are the opposite case, see §2.5.

**What this costs.** `contracts/openapi.yaml` can no longer constrain the field to
`enum: [bug, idea, question]` — it becomes a plain `string`, validated by a database lookup. The
polyglot contract loses one of the checks that stands in for a compiler here. It is the
unavoidable price of per-installation types: a generated spec cannot describe a list that each
installation chooses. The widget pays it too — it has to read the available types rather than
hard-coding three buttons.

**Defaults shipped by the installer:** `bug` · `idea` · `question` (§2.7). There is deliberately
**no `other`**: a catch-all value attracts everything and stops the field from meaning anything.

### 2.5 `FeedbackStatus` — where we are with it

`new` · `triaged` · `planned` · `in_progress` · `done` · `declined`

Set by the owner, never by the submitter. `new` on creation.

**A PHP backed enum in a `string(20)` column — deliberately not a table, unlike `type` (§2.4).**
Code branches on these values: the public roadmap shows `planned`, `in_progress` and `done` and
hides the rest, and a state machine will eventually constrain the transitions. Turning them into
rows means the application can no longer know them, so it needs a `is_shown_on_roadmap` column,
then a `is_terminal` one, then an ordering, then a transition table — a workflow engine, written
to avoid an enum. Configurability is worth having where it costs a table; here it costs a
subsystem.

The full set is defined now even though milestone 1 only ever writes `new`. The three middle
values are what the public roadmap displays at milestone 5, and enum values are cheap to define
in advance and awkward to retrofit into a migration later.

No state machine is enforced in this milestone — any transition is allowed. Constraining it is
a milestone-5 concern, once the roadmap gives the transitions a meaning.

### 2.6 `SubmitterContext` — a Doctrine embeddable

Five nullable columns, grouped into one value object rather than scattered across the entity:

| Field | Column | Type | Notes |
|---|---|---|---|
| `name` | `submitter_name` | `string(120)` | |
| `email` | `submitter_email` | `string(180)` | validated as an e-mail when present |
| `sourceUrl` | `submitter_source_url` | `string(2048)` | the page the widget was on |
| `locale` | `submitter_locale` | `string(12)` | BCP 47, e.g. `fr-FR` |
| `userAgent` | `submitter_user_agent` | `string(512)` | |

They live in the same table — an embeddable is a mapping concept, not a join. The alternative
was five plain fields on `Feedback`, which works and is one concept lighter; the embeddable wins
because this cluster travels together, is entirely optional, and will be reused the day contact
messages get their own entity.

**Everything here is optional, including the e-mail.** Anonymous feedback is the default case.

`userAgent` is read from the request headers by the controller and **never trusted from the
request body** — see §3.2.

### 2.7 Seed data and fixtures — two different jobs

These get confused, and confusing them is how a production database gets purged.

**Reference data — the default feedback types.** Inserted by the Doctrine migration that creates
the `feedback_type` table, as a plain idempotent `INSERT`. `doctrine:migrations:migrate` is
already the documented installation step, so someone installing the project gets a working
application with no extra command to forget. `doctrine/doctrine-fixtures-bundle` is the wrong
tool for this: `doctrine:fixtures:load` **purges the database by default**, which makes it dev
tooling, not an installer.

The rejected alternative was a dedicated `app:install` console command. It makes the mechanism
more visible, which this project usually values — but it is a step that gets skipped, and the
failure mode is an application whose widget offers zero types.

**Development data — products and sample feedback.** That is what
`doctrine/doctrine-fixtures-bundle` is for, and it becomes a dependency in this milestone: there
is no product creation endpoint, so without a fixture there is nothing to attach a feedback item
to. Dev and test only, never part of an installation.

**Consequence for the test suite: tests run the migrations, not `doctrine:schema:create`.**
Building the schema from entity metadata skips the seeded types, and every functional test on
`POST /api/feedback` would fail on a type that does not exist. Running migrations instead means
the migrations themselves are exercised on every CI run — a check this project would otherwise
never have.

---

## 3. `POST /api/feedback`

Creates a feedback item. Called by `ingest` in the finished architecture, by `curl` today.

### 3.1 Request

```http
POST /api/feedback
Content-Type: application/json
```

```json
{
  "product": "my-little-library",
  "type": "bug",
  "title": "Search returns nothing",
  "message": "Searching for an author I know is in the library returns an empty list.",
  "submitter": {
    "name": "Ada",
    "email": "ada@example.com"
  },
  "context": {
    "sourceUrl": "https://library.example.com/search?q=lovelace",
    "locale": "fr-FR"
  }
}
```

| Field | Required | Rule |
|---|---|---|
| `product` | yes | must match an existing `Product.slug` |
| `type` | yes | must match an **active** `FeedbackType.slug` |
| `message` | yes | 10–5000 characters after trimming |
| `title` | no | ≤ 160 characters |
| `submitter.name` | no | ≤ 120 characters |
| `submitter.email` | no | valid e-mail, ≤ 180 characters |
| `context.sourceUrl` | no | valid absolute URL, ≤ 2048 characters |
| `context.locale` | no | ≤ 12 characters |

**`status` is not accepted in the payload.** It is server-owned. A client that sends it gets a
422, not a silent ignore — quietly dropping a field the caller believed in is how integrations
rot.

### 3.2 Fields the server sets itself

- `status` → `new`
- `createdAt` → now
- `submitter.userAgent` → from the `User-Agent` header

The user agent is a header, so the body has no business carrying one. `ingest` will forward the
original header rather than inventing a body field.

### 3.3 Responses

**`201 Created`**, with a `Location` header pointing at the created item and the resource as
body (§5).

**`400 Bad Request`** — the body is not valid JSON, or is not an object. This is a malformed
request, not a rejected one, so it does not go through the validator.

```json
{ "error": "malformed_json", "message": "Request body is not valid JSON." }
```

**`422 Unprocessable Content`** — the body parsed but failed validation. Every violation is
reported, not just the first: a caller fixing one field at a time across five round-trips is a
bad API.

```json
{
  "error": "validation_failed",
  "violations": [
    { "field": "message", "message": "This value is too short. It should have 10 characters or more." },
    { "field": "product", "message": "No product matches this identifier." }
  ]
}
```

An unknown `product` slug is a **422 violation on the `product` field, not a 404.** The request
reached a route that exists; what is wrong is a value inside it. Uniformity matters more here
than protocol purism — a caller with one error shape to parse writes less code.

---

## 4. `GET /api/feedback`

Lists feedback, newest first. Back-office and (later) `mcp` read through this.

### 4.1 Query parameters

| Parameter | Default | Rule |
|---|---|---|
| `product` | — | filter by product slug |
| `type` | — | a `FeedbackType.slug`, **active or not** |
| `status` | — | one of the enum values |
| `page` | `1` | ≥ 1 |
| `perPage` | `20` | 1–100, clamped rather than rejected |

An unknown `type` or `status` is a `422` with the same envelope as §3.3. An out-of-range
`perPage` is clamped silently — it is a pagination hint, not data.

**Filtering accepts inactive types**, unlike creation (§3.1). Retiring a type must not make the
feedback already filed under it unreachable in the back-office.

### 4.2 Response — `200 OK`

```json
{
  "items": [ /* §5 */ ],
  "page": 1,
  "perPage": 20,
  "total": 137
}
```

`total` is the count matching the filters, ignoring pagination. It costs a second query, and
the back-office needs it to render anything resembling a pager.

---

## 5. Feedback representation

One shape, used by both endpoints. Serialization is hand-written in a small
`FeedbackResponse` DTO rather than driven by serializer groups on the entity — the entity's job
is persistence, and letting a public payload shape leak into it is how a field ends up exposed
by accident.

```json
{
  "id": "0198f2c1-6e3a-7b41-9c2d-3f5a8b7e1d40",
  "product": { "slug": "my-little-library", "name": "My Little Library" },
  "type": { "slug": "bug", "label": "Bug" },
  "status": "new",
  "title": "Search returns nothing",
  "message": "Searching for an author I know is in the library returns an empty list.",
  "submitter": { "name": "Ada", "email": "ada@example.com" },
  "context": { "sourceUrl": "https://library.example.com/search?q=lovelace", "locale": "fr-FR" },
  "createdAt": "2026-08-17T09:14:22+00:00"
}
```

`type` is an object rather than a bare string, for the same reason `product` is: the slug is the
stable machine value, the label is what a human reads, and since types are per-installation data
(§2.4) no consumer can derive one from the other. A widget that received `"bug"` alone would have
to keep its own translation table — which is precisely what putting types in the database was
meant to avoid.

`submitter.userAgent` is **not** in the representation. It is stored for spam triage, not for
display, and the endpoint has no authentication yet.

Absent optional values are serialized as `null`, never omitted. A consumer that can rely on a
stable set of keys is a consumer that needs fewer conditionals — and the widget's TypeScript
types get to be honest.

---

## 6. Back-office listing

One Twig page, `GET /admin/feedback`. **Requires installing `symfony/twig-bundle`, which is not
currently a dependency.**

A table: date, product, type, status, title (falling back to the first line of `message`),
submitter. Filters on product, type and status, mapped onto the same query parameters as §4.
Server-rendered, reading the repository directly — it does not call its own HTTP API.

No authentication in this milestone, so the route is as open as the API. Same caveat, §7.

---

## 7. Known holes, deliberately left open

- **No authentication anywhere.** Both API routes and the back-office are open. Milestone 1 runs
  on localhost only. **This blocks any deployment**, including a staging one.
- **No rate limiting, no spam filtering, no origin allow-list.** That is `ingest`'s job and it
  does not exist yet.
- **No status-transition rules.** Any status can move to any other.
- **No product CRUD, and no feedback-type CRUD.** Products come from a fixture, types from the
  migration (§2.7). Changing either means SQL until the back-office grows the screens — which is
  a gap in the configurability argument for §2.4, not a contradiction of it.
- **No endpoint exposing the list of types.** The widget will need one; it is specified with the
  widget, at milestone 2, because its shape depends on whether types end up per-product.
- **`GET /api/feedback` exposes submitter e-mail addresses** with no auth in front of it. Fine on
  a laptop, unacceptable anywhere else.

---

## 8. Order of implementation

1. Enable `nelmio/api-doc-bundle` — installed, but the Flex recipe was never applied: no entry in
   `bundles.php`, no configuration, no route. Nothing can generate `contracts/openapi.yaml` until
   this is fixed, so the CI contract check cannot pass. It goes first.
2. Entities, embeddable, the `status` enum, repositories.
3. Doctrine migration — schema plus the seeded default types — applied against the Compose
   database. Switch the test bootstrap to run migrations instead of `doctrine:schema:create`, and
   add `doctrine/doctrine-fixtures-bundle` with a product fixture (§2.7).
4. `POST /api/feedback` — DTO, validation, error envelope, functional tests.
5. `GET /api/feedback` — filters, pagination, functional tests.
6. Twig plus the back-office listing.
7. Generate `contracts/openapi.yaml` and commit it, closing the CI loop.

Steps 1 and 2–3 are one pull request each; 4 and 5 are one each; 6 and 7 close the milestone.
