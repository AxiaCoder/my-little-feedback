<!--
Keep this short. Three sections, a few lines each — the point is to make the
diff readable, not to write a report. Delete any section that has nothing to say.
-->

## What

<!-- What changes, in one or two sentences. -->

## Why

<!--
The reasoning a diff cannot carry: what was rejected, what constraint forced
this shape. This is the part worth reading in six months.
-->

## Verification

<!--
What was actually run, and what it returned. "Should work" is not verification.
-->

- [ ] `docker compose exec core composer test`
- [ ] `docker compose exec core composer stan`
- [ ] `docker compose exec core composer cs`
- [ ] `contracts/openapi.yaml` regenerated if any API response shape moved
