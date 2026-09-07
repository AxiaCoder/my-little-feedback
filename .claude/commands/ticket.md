---
description: Work an issue — state what "done" looks like, the author implements it
argument-hint: [issue number]
allowed-tools: Bash(gh issue view:*), Bash(gh issue list:*), Bash(gh pr list:*), Read, Grep, Glob
---

The author implements the issue. Your job is to state what "done" looks like, and
then stop. This is the working agreement for this repository (see `CLAUDE.md`,
"Ground rules"), not a style preference.

## Pick the issue

`$1` is an issue number. If it is empty, list the open issues of the current
milestone and take the lowest-numbered one that is not blocked by another open
issue.

Read the issue, and read the section of `docs/specs/` it points at. The issue's
own task list is written for an implementer and is deliberately more verbose than
what you are about to write.

## Answer with this, and nothing else

Reply in the language the author is writing in. Keep it under 25 lines.

1. **One line** naming the issue and the branch to open for it.
2. **The observable result.** Response shapes copied from the spec are welcome —
   they are the contract, not code.
3. **The behaviours to obtain**, as a numbered list. Each one is something the
   author can check from outside: a request in, a response out. No more than
   eight.
4. **The tests expected**, in one line.
5. **Done when:** the commands that have to pass.

## Never

- No code, no snippets, no diffs, no pseudo-code, not even "roughly this".
- No names of classes, methods or files to create, and no implementation order.
- No design advice, no trade-off discussion, no alternatives considered.
- No "notes", "watch out for" or "by the way" section.
- No closing question, and no offer to help further. The author will ask.

If a genuine contradiction in the spec makes an expectation impossible to state,
say so in one sentence and state the rest anyway.
