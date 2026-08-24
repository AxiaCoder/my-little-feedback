<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Who filed a feedback item and from where — all of it optional, because
 * anonymous feedback is the default case (spec 01 §2.6).
 *
 * An embeddable rather than five plain fields on {@see Feedback}: the cluster
 * travels together and will be reused the day contact messages get their own
 * entity.
 */
#[ORM\Embeddable]
class SubmitterContext
{
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $name;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    /** The page the widget was on. */
    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $sourceUrl;

    /** BCP 47, e.g. `fr-FR`. */
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $locale;

    /**
     * Read from the request headers by the controller and **never** trusted from
     * the request body (spec 01 §3.2). Stored for spam triage, not for display:
     * it is absent from the API representation.
     */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent;

    public function __construct(
        ?string $name = null,
        ?string $email = null,
        ?string $sourceUrl = null,
        ?string $locale = null,
        ?string $userAgent = null,
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->sourceUrl = $sourceUrl;
        $this->locale = $locale;
        $this->userAgent = $userAgent;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
