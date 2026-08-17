<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the plumbing every later test depends on without ever mentioning it.
 *
 * Both assertions read as tautologies until the day they fail. The first one
 * failing means the suite is writing into the development database; the second
 * means the schema stopped coming from the migrations, and with it the feedback
 * types they seed (spec 01 §2.7).
 */
final class TestDatabaseTest extends KernelTestCase
{
    public function testTheSuiteRunsAgainstItsOwnDatabase(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);

        self::assertSame('mlf_test', $connection->getDatabase());
    }

    public function testTheSchemaWasBuiltByTheMigrations(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);

        self::assertContains(
            'doctrine_migration_versions',
            $connection->createSchemaManager()->listTableNames(),
        );
    }
}
