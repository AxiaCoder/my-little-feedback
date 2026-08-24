<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FeedbackType;
use App\Repository\FeedbackTypeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The default feedback types are reference data, seeded by the migration that
 * creates their table (spec 01 §2.7). This asserts they are actually there,
 * which is the same as asserting that the suite built its schema by running the
 * migrations rather than from entity metadata.
 *
 * It is also what stops `POST /api/feedback` from failing on a type that does
 * not exist, later in this milestone.
 */
final class DefaultFeedbackTypesTest extends KernelTestCase
{
    public function testTheThreeDefaultTypesAreSeededAndActive(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(FeedbackTypeRepository::class);
        $types = $repository->findBy([], ['position' => 'ASC']);

        self::assertSame(
            ['bug', 'idea', 'question'],
            array_map(static fn (FeedbackType $type): string => $type->getSlug(), $types),
        );

        foreach ($types as $type) {
            self::assertTrue($type->isActive(), sprintf('The seeded type "%s" should be active.', $type->getSlug()));
        }
    }

    /**
     * There is deliberately no `other`: a catch-all attracts everything and stops
     * the field from meaning anything (spec 01 §2.4). Worth a test, because the
     * temptation to add one comes back.
     */
    public function testThereIsNoCatchAllType(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(FeedbackTypeRepository::class);

        self::assertNull($repository->findOneBy(['slug' => 'other']));
    }
}
