<?php

declare(strict_types=1);

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value has to match an **active** {@see \App\Entity\FeedbackType} slug.
 *
 * Active is the difference from the listing filter, which accepts retired types
 * too (spec 01 §4.1): retiring a type stops new feedback from being filed under
 * it without hiding what was already filed.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ActiveFeedbackTypeSlug extends Constraint
{
    public string $message = 'No active feedback type matches this identifier.';
}
