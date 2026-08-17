<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The milestone-1 schema: products, feedback types, feedback.
 *
 * The default feedback types are seeded here rather than by a fixture, and that
 * is the one hand-written part of this file. `doctrine:migrations:migrate` is
 * already the documented installation step, so someone installing the project
 * gets a working application with no extra command to forget --
 * `doctrine:fixtures:load` **purges the database by default**, which makes it dev
 * tooling rather than an installer (spec 01 §2.7).
 *
 * A dedicated `app:install` command was the rejected alternative. It makes the
 * mechanism more visible, which this project usually values, but it is a step
 * that gets skipped, and the failure mode is an application whose widget offers
 * zero types.
 */
final class Version20260817203759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product, feedback_type and feedback; seed the default feedback types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feedback (id UUID NOT NULL, status VARCHAR(20) DEFAULT \'new\' NOT NULL, title VARCHAR(160) DEFAULT NULL, message TEXT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, submitter_name VARCHAR(120) DEFAULT NULL, submitter_email VARCHAR(180) DEFAULT NULL, submitter_source_url VARCHAR(2048) DEFAULT NULL, submitter_locale VARCHAR(12) DEFAULT NULL, submitter_user_agent VARCHAR(512) DEFAULT NULL, product_id UUID NOT NULL, type_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D22944584584665A ON feedback (product_id)');
        $this->addSql('CREATE INDEX IDX_D2294458C54C8C93 ON feedback (type_id)');
        $this->addSql('CREATE INDEX idx_feedback_product_created_at ON feedback (product_id, created_at)');
        $this->addSql('CREATE INDEX idx_feedback_status ON feedback (status)');
        $this->addSql('CREATE TABLE feedback_type (id UUID NOT NULL, slug VARCHAR(30) NOT NULL, label VARCHAR(60) NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_feedback_type_slug ON feedback_type (slug)');
        $this->addSql('CREATE TABLE product (id UUID NOT NULL, slug VARCHAR(60) NOT NULL, name VARCHAR(120) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_slug ON product (slug)');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D22944584584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458C54C8C93 FOREIGN KEY (type_id) REFERENCES feedback_type (id) ON DELETE RESTRICT NOT DEFERRABLE');

        // Reference data, in the same file that creates its table.
        //
        // Everything above is DDL and everything here is DML, and both go through
        // addSql() on purpose. The executor queues *all* addSql() statements
        // before any DDL derived from mutating the $schema argument
        // (DbalExecutor::executeMigration), so building the tables through the
        // schema builder while inserting through addSql() would run this INSERT
        // against tables that do not exist yet.
        //
        // The identifiers are fixed rather than generated: PostgreSQL 16 has no
        // UUID v7 function, and keys that differed per installation could not be
        // referenced from a later migration. They are genuine v7 values,
        // generated once.
        //
        // ON CONFLICT keeps each statement idempotent, so re-running this against
        // a database that already carries these slugs is a no-op rather than a
        // failure.
        //
        // There is deliberately no `other`: a catch-all value attracts everything
        // and stops the field from meaning anything (spec 01 §2.4).
        $defaultTypes = [
            ['01a01171-fd73-7dd5-9838-f317987db226', 'bug', 'Bug', 0],
            ['01a01171-fd7c-7be9-b0c4-443c8717c549', 'idea', 'Idea', 1],
            ['01a01171-fd7c-7c59-b0c4-443c87ee6f5b', 'question', 'Question', 2],
        ];

        foreach ($defaultTypes as [$id, $slug, $label, $position]) {
            // Bound parameters rather than values interpolated into the string.
            // Nothing here comes from outside this file, so there is no injection
            // to prevent; the point is that this is the form to reach for on the
            // day a migration has to transform data it did not choose.
            //
            // `is_active` stays a literal: it is the state every default type
            // starts in, not a value that varies per row.
            $this->addSql(
                'INSERT INTO feedback_type (id, slug, label, position, is_active)'
                .' VALUES (?, ?, ?, ?, true) ON CONFLICT (slug) DO NOTHING',
                [$id, $slug, $label, $position],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback DROP CONSTRAINT FK_D22944584584665A');
        $this->addSql('ALTER TABLE feedback DROP CONSTRAINT FK_D2294458C54C8C93');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE feedback_type');
        $this->addSql('DROP TABLE product');
    }
}
