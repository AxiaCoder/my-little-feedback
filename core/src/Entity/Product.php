<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A product feedback is filed against — My Little Library, My Little Trivia, and
 * whatever comes next. One instance serves several of them (spec 01 §2.2).
 *
 * There is no creation endpoint in milestone 1: products come from a fixture in
 * development and from SQL in an installation, until the back-office grows the
 * screens for them.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uniq_product_slug', columns: ['slug'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /**
     * The public `data-product="…"` value.
     *
     * An identifier, never a credential: anyone who can read the widget's script
     * tag can read this and post to the endpoint. Every protection is
     * server-side, which is what justifies `ingest` existing at all.
     */
    #[ORM\Column(length: 60)]
    private string $slug;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(string $slug, string $name)
    {
        $this->id = Uuid::v7();
        $this->slug = $slug;
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
