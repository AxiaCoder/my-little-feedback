<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FeedbackRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One thing a user told us about one product (spec 01 §2.3).
 */
#[ORM\Entity(repositoryClass: FeedbackRepository::class)]
#[ORM\Table(name: 'feedback')]
// Serves the back-office listing, which is filtered by product and ordered by
// date. A btree scans backwards at the same cost, so the index carries no
// direction of its own.
#[ORM\Index(name: 'idx_feedback_product_created_at', columns: ['product_id', 'created_at'])]
// For the roadmap queries that arrive at milestone 5.
#[ORM\Index(name: 'idx_feedback_status', columns: ['status'])]
class Feedback
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /**
     * `RESTRICT`, not a cascade: deleting a product that still holds feedback
     * should fail loudly rather than silently destroy the history. Product
     * deletion is not implemented yet, so the safe direction costs nothing.
     */
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'RESTRICT')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: FeedbackType::class)]
    #[ORM\JoinColumn(name: 'type_id', nullable: false, onDelete: 'RESTRICT')]
    private FeedbackType $type;

    #[ORM\Column(length: 20, enumType: FeedbackStatus::class, options: ['default' => 'new'])]
    private FeedbackStatus $status;

    /**
     * Nullable on purpose. The widget's job is to make reporting cheap, and a
     * mandatory subject line is the field people abandon a form on. The
     * back-office falls back to the first line of the message when it is absent.
     */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Embedded(class: SubmitterContext::class, columnPrefix: 'submitter_')]
    private SubmitterContext $submitter;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** Set when the status or the content changes. */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(
        Product $product,
        FeedbackType $type,
        string $message,
        ?string $title = null,
        ?SubmitterContext $submitter = null,
    ) {
        $this->id = Uuid::v7();
        $this->product = $product;
        $this->type = $type;
        $this->message = $message;
        $this->title = $title;
        $this->submitter = $submitter ?? new SubmitterContext();
        // Server-owned, never accepted from the payload (spec 01 §3.1).
        $this->status = FeedbackStatus::New;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getType(): FeedbackType
    {
        return $this->type;
    }

    public function getStatus(): FeedbackStatus
    {
        return $this->status;
    }

    /**
     * No transition is refused in this milestone. Constraining them is a
     * milestone-5 concern, once the roadmap gives them a meaning (spec 01 §2.5).
     */
    public function setStatus(FeedbackStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSubmitter(): SubmitterContext
    {
        return $this->submitter;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
