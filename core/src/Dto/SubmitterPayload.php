<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The `submitter` object of the creation payload (spec 01 §3.1).
 *
 * `userAgent` is deliberately absent: it is a header, so the body has no
 * business carrying one (§3.2). A payload that sends it anyway is refused by
 * {@see CreateFeedbackRequest::fromBody()} rather than ignored.
 */
final class SubmitterPayload
{
    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Length(max: 120),
    ])]
    public mixed $name = null;

    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Length(max: 180),
        new Assert\Email(),
    ])]
    public mixed $email = null;
}
