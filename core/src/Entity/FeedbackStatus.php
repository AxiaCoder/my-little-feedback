<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Where we are with a feedback item. Set by the owner, never by the submitter.
 *
 * A PHP backed enum in a `string(20)` column, deliberately not a table — the
 * opposite call from {@see FeedbackType}, for the reason given in spec 01 §2.5:
 * code branches on these values. The public roadmap shows `planned`,
 * `in_progress` and `done` and hides the rest, and a state machine will
 * eventually constrain the transitions.
 *
 * Turning them into rows means the application can no longer know them, so it
 * needs an `is_shown_on_roadmap` column, then an `is_terminal` one, then an
 * ordering, then a transition table — a workflow engine, written to avoid an
 * enum. Configurability is worth having where it costs a table; here it costs a
 * subsystem.
 *
 * The full set is defined now even though milestone 1 only ever writes `New`.
 * The middle values are what the roadmap displays at milestone 5, and enum
 * values are cheap to define in advance and awkward to retrofit into a migration
 * later.
 */
enum FeedbackStatus: string
{
    case New = 'new';
    case Triaged = 'triaged';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Declined = 'declined';
}
