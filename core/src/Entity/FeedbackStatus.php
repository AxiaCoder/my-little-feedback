<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Where we are with a feedback item. Set by the owner, never by the submitter.
 *
 * A PHP backed enum rather than a table, unlike {@see FeedbackType} — spec 01 §2.5.
 *
 * The full set is defined now even though nothing writes anything but `New`
 * yet: enum values are cheap to declare in advance and awkward to retrofit into
 * a migration later.
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
