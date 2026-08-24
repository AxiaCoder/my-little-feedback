<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes the default feedback types that Version20260817203759 seeded.
 *
 * They were reference data in a schema migration, and spec 01 §2.7 no longer
 * treats them as reference data at all: an installation picks its own set
 * through the back-office, and the defaults are development data that belongs in
 * `AppFixtures`. A migration that has been applied is never edited, so the rows
 * leave through a migration of their own rather than by rewriting the one that
 * inserted them.
 */
final class Version20260824170957 extends AbstractMigration
{
    /**
     * The identifiers, not the slugs. A `bug` type an operator created by hand
     * is theirs and has a different key; only the three literals written into
     * the earlier migration are ours to take back.
     */
    private const SEEDED_IDS = [
        '01a01171-fd73-7dd5-9838-f317987db226',
        '01a01171-fd7c-7be9-b0c4-443c8717c549',
        '01a01171-fd7c-7c59-b0c4-443c87ee6f5b',
    ];

    public function getDescription(): string
    {
        return 'Remove the seeded default feedback types; they are fixture data now (spec 01 §2.7)';
    }

    public function up(Schema $schema): void
    {
        // `NOT EXISTS` rather than a bare DELETE. `feedback.type_id` is
        // ON DELETE RESTRICT, so a development database that already carries
        // feedback filed under one of these types would fail this migration
        // outright. Keeping a row somebody's data depends on is the right
        // outcome; the fixtures purge it soon enough on the next load.
        foreach (self::SEEDED_IDS as $id) {
            $this->addSql(
                'DELETE FROM feedback_type WHERE id = ?'
                .' AND NOT EXISTS (SELECT 1 FROM feedback WHERE feedback.type_id = feedback_type.id)',
                [$id],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Re-seeding the default types would put them back in a migration, which is what this removed. '
            .'Load them with `composer fixtures`.',
        );
    }
}
