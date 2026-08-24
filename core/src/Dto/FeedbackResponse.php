<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Feedback;
use DateTimeInterface;
use JsonSerializable;

/**
 * The one feedback shape both endpoints return (spec 01 §5).
 *
 * Hand-written rather than serializer groups on the entity: the entity's job is
 * persistence, and letting a public payload shape leak into it is how a field
 * ends up exposed by accident. `submitter.userAgent` is the field in question —
 * it is stored for spam triage and deliberately absent here.
 *
 * Absent optional values are serialized as `null`, never omitted, so a consumer
 * can rely on a stable set of keys.
 */
final readonly class FeedbackResponse implements JsonSerializable
{
    private function __construct(private Feedback $feedback)
    {
    }

    public static function fromEntity(Feedback $feedback): self
    {
        return new self($feedback);
    }

    /**
     * `submitter` and `context` are two objects here and one embeddable in the
     * entity. The split is the API's: who filed it is a different question from
     * where they filed it, and the widget fills them from different places.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $product = $this->feedback->getProduct();
        $type = $this->feedback->getType();
        $submitter = $this->feedback->getSubmitter();

        return [
            'id' => (string) $this->feedback->getId(),
            'product' => [
                'slug' => $product->getSlug(),
                'name' => $product->getName(),
            ],
            'type' => [
                'slug' => $type->getSlug(),
                'label' => $type->getLabel(),
            ],
            'status' => $this->feedback->getStatus()->value,
            'title' => $this->feedback->getTitle(),
            'message' => $this->feedback->getMessage(),
            'submitter' => [
                'name' => $submitter->getName(),
                'email' => $submitter->getEmail(),
            ],
            'context' => [
                'sourceUrl' => $submitter->getSourceUrl(),
                'locale' => $submitter->getLocale(),
            ],
            'createdAt' => $this->feedback->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
