# contracts

`openapi.yaml` is **generated from `core/`** and committed here. Do not edit it by hand.

```bash
docker compose exec core php bin/console nelmio:apidoc:dump --format=yaml > contracts/openapi.yaml
```

CI regenerates it and fails if the result differs from what is committed.

## Why this exists

In a single-language repository the compiler catches you: change the shape of an object and every wrong call fails to build.

Here the same structure is described in PHP, in Go and in Python, and **nothing reports that they have drifted apart**. You find out in production.

This file is the shared contract, and the CI check is what replaces the compiler. Without it, splitting this project across four languages is a trap rather than a structure.
