#!/bin/sh
# Session pre-check.
#
# Every useful command in this project goes through the container stack:
# the database lives in Compose, and so do the migrations and the OpenAPI dump.
# Starting a session without a running engine means discovering it several
# commands later, after a confusing failure. This says so up front instead.
#
# Output is a SessionStart hook JSON payload: `systemMessage` reaches the human,
# `additionalContext` reaches the model so it stops proposing commands that
# cannot possibly work.

emit() {
    # $1 = message for the user, $2 = context for the model
    printf '{"systemMessage":"%s","hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$1" "$2"
}

if ! command -v docker >/dev/null 2>&1; then
    emit \
        "No container engine found. Install Rancher Desktop (dockerd mode) before running the stack." \
        "PRE-CHECK FAILED: the docker CLI is not on PATH. The database, the migrations and the OpenAPI dump all run through Compose, so none of them will work. Tell the user to install Rancher Desktop in dockerd mode; do not propose docker or compose commands until it is available."
    exit 0
fi

if ! docker info >/dev/null 2>&1; then
    emit \
        "Container engine installed but not responding — start Rancher Desktop, then retry." \
        "PRE-CHECK FAILED: the docker CLI exists but the daemon is not responding. Rancher Desktop is most likely not started. Tell the user to start it and wait for the engine to come up; do not propose docker or compose commands until it responds."
    exit 0
fi

exit 0
