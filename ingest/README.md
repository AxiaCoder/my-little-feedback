# ingest — Go

**Not implemented yet. Milestone 4.** Until then, the widget posts directly to `core`.

## Role

The only surface exposed to the internet **in write mode**. It receives widget submissions, validates them, enforces rate limits, filters spam, and forwards to the internal `core` API.

**It never writes to the database.** `core` owns the schema; this service is a doorman, not storage.

## Why it is a separate service

The widget is public, so the product identifier it carries (`data-product="..."`) is an identifier, **not a credential**. Anyone can read the script and post to this endpoint. All protection is therefore server-side, and isolating that surface from the application has value on its own.

It is also, deliberately, **off the critical path**: if this component stalls, everything else still runs. That is what makes it the right place for a language being learned.
