<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FeedbackTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * What the submitter is telling us: a bug, an idea, a question.
 *
 * A table rather than a PHP enum, so that an installation can choose its own
 * set without patching PHP — spec 01 §2.4, and the contrast with
 * {@see FeedbackStatus}.
 */
#[ORM\Entity(repositoryClass: FeedbackTypeRepository::class)]
#[ORM\Table(name: 'feedback_type')]
#[ORM\UniqueConstraint(name: 'uniq_feedback_type_slug', columns: ['slug'])]
class FeedbackType
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** The stable machine value carried by the API payload. */
    #[ORM\Column(length: 30)]
    private string $slug;

    /** What the widget displays. */
    #[ORM\Column(length: 60)]
    private string $label;

    /** Ordering in the widget; ties are broken by label. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position;

    /**
     * A type that has ever been used can never be deleted, since {@see Feedback}
     * references it with `RESTRICT`. Deactivating is the way to retire one: it
     * disappears from the widget and from the creation endpoint, while the
     * feedback already filed under it stays readable.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $isActive;

    public function __construct(string $slug, string $label, int $position = 0, bool $isActive = true)
    {
        $this->id = Uuid::v7();
        $this->slug = $slug;
        $this->label = $label;
        $this->position = $position;
        $this->isActive = $isActive;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
