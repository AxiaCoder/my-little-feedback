<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Feedback;
use App\Entity\FeedbackType;
use App\Entity\Product;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The fixtures are the only source of feedback types now (spec 01 §2.7), so one
 * that stopped loading would take the development environment with it, and say
 * nothing until someone opened the back-office and found it empty.
 *
 * Everything happens inside a transaction that is always rolled back. The purge
 * makes the counts independent of whatever ran before; the rollback makes sure
 * the six feedback items written here are not inherited by whatever runs after.
 */
final class AppFixturesTest extends KernelTestCase
{
    public function testTheFixturesLoadTheirProductsTypesAndSampleFeedback(): void
    {
        self::bootKernel();

        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $manager->getConnection();

        $connection->beginTransaction();

        try {
            (new ORMPurger($manager))->purge();
            (new AppFixtures())->load($manager);

            self::assertCount(3, $manager->getRepository(FeedbackType::class)->findAll());
            self::assertCount(2, $manager->getRepository(Product::class)->findAll());
            self::assertCount(6, $manager->getRepository(Feedback::class)->findAll());

            // No catch-all type, for the reason spec 01 §2.4 gives. The
            // temptation to add one comes back, so it is worth an assertion.
            self::assertNull($manager->getRepository(FeedbackType::class)->findOneBy(['slug' => 'other']));
        } finally {
            $connection->rollBack();
            $manager->clear();
        }
    }
}
