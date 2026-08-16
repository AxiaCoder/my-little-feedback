# mcp — Python

**Not implemented yet. Milestone 3.**

## Role

An MCP server exposing feedback to AI clients (Claude and any other MCP-capable assistant), in **read and search only**.

It calls the `core` HTTP API. It does not touch the database.

## Scope boundary

**This server does not create tickets in an issue tracker.** Turning raw feedback into a well-scoped ticket is a judgement call that belongs wherever that judgement is already made. Exposing feedback cleanly is where this component's job ends.

## Transport

**Streamable HTTP**, not HTTP+SSE — the latter is deprecated in the MCP specification.

Built on the official Python SDK.
