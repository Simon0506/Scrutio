<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014141458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert case content tables to InnoDB and add foreign keys.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE case_file ENGINE = InnoDB');
        $this->addSql('ALTER TABLE evidence_document ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_metadata ENGINE = InnoDB');
        $this->addSql('ALTER TABLE solution_schema ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_metadata ADD CONSTRAINT FK_C0D5C54DC33F7837 FOREIGN KEY (document_id) REFERENCES evidence_document (id)');
        $this->addSql('ALTER TABLE evidence_document ADD CONSTRAINT FK_5F89F0923C688C67 FOREIGN KEY (case_file_id) REFERENCES case_file (id)');
        $this->addSql('ALTER TABLE solution_schema ADD CONSTRAINT FK_CB7896343C688C67 FOREIGN KEY (case_file_id) REFERENCES case_file (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_metadata DROP FOREIGN KEY FK_C0D5C54DC33F7837');
        $this->addSql('ALTER TABLE evidence_document DROP FOREIGN KEY FK_5F89F0923C688C67');
        $this->addSql('ALTER TABLE solution_schema DROP FOREIGN KEY FK_CB7896343C688C67');
    }
}
