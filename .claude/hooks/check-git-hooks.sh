#!/bin/sh
# Session pre-check — makes sure the repository's git hooks are active.
#
# Git never installs hooks on clone: `.githooks/` is versioned, but until
# core.hooksPath points at it the directory is inert. That failure is silent and
# only shows up as a commit landing straight on main, which is precisely what the
# hooks exist to prevent.
#
# So this configures it rather than merely reporting it. The setting is local to
# the clone, it changes nothing outside this repository, and leaving the session
# to hope someone runs the command by hand defeats the point of having hooks.

emit() {
    # $1 = message for the user, $2 = context for the model
    printf '{"systemMessage":"%s","hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$1" "$2"
}

command -v git >/dev/null 2>&1 || exit 0
git rev-parse --git-dir >/dev/null 2>&1 || exit 0

current=$(git config --local --get core.hooksPath 2>/dev/null)

[ "$current" = ".githooks" ] && exit 0

if git config --local core.hooksPath .githooks 2>/dev/null; then
    emit \
        "Git hooks activated for this clone (core.hooksPath -> .githooks). Commits on main are now refused, and commit messages are checked against Conventional Commits." \
        "The repository git hooks were not active and have just been enabled. Commits directly on main will be refused and commit subjects must follow Conventional Commits: <type>(<optional scope>): <subject>. Work on a branch and open a pull request."
else
    emit \
        "Could not set core.hooksPath — the repository hooks are inactive, so nothing prevents a commit on main. Run: git config core.hooksPath .githooks" \
        "PRE-CHECK FAILED: core.hooksPath could not be set, so .githooks/ is inert and commits on main are not blocked. Ask the user to run 'git config core.hooksPath .githooks' and be careful not to commit on main in the meantime."
fi

exit 0
