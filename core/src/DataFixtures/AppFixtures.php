<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Development and test data only — never part of an installation.
 *
 * `doctrine:fixtures:load` purges the database before loading, which is exactly
 * why the default feedback types do NOT live here: they are reference data, and
 * they are seeded by the migration that creates their table instead.
 * See docs/specs/01-core-data-model.md §2.7.
 *
 * Fills up with products and sample feedback once the entities exist.
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->flush();
    }
}
