<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The `context` object of the creation payload (spec 01 §3.1).
 */
final class ContextPayload
{
    /**
     * `requireTld: false` on purpose. A widget under development posts from
     * `http://localhost:3000`, which has no top-level domain, and refusing the
     * one environment the widget is written in would be an odd way to validate
     * a field that exists to help triage.
     */
    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Length(max: 2048),
        new Assert\Url(protocols: ['http', 'https'], requireTld: false),
    ])]
    public mixed $sourceUrl = null;

    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Length(max: 12),
    ])]
    public mixed $locale = null;
}
