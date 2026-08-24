<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The milestone-1 schema: products, feedback types, feedback.
 *
 * Schema only. The feedback types are created by `AppFixtures` in development
 * and through the back-office in an installation (spec 01 §2.7).
 */
final class Version20260817203759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the product, feedback_type and feedback tables';
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
