<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014141359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE case_file (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, synopsis LONGTEXT NOT NULL, difficulty VARCHAR(24) NOT NULL, season_number INT NOT NULL, is_free TINYINT NOT NULL, display_order INT NOT NULL, UNIQUE INDEX UNIQ_D87ECA3989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_metadata (id INT AUTO_INCREMENT NOT NULL, metadata_key VARCHAR(100) NOT NULL, metadata_value LONGTEXT NOT NULL, display_order INT NOT NULL, document_id INT NOT NULL, INDEX IDX_C0D5C54DC33F7837 (document_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evidence_document (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, document_type VARCHAR(32) NOT NULL, template_key VARCHAR(64) NOT NULL, content JSON NOT NULL, display_order INT NOT NULL, case_file_id INT NOT NULL, INDEX IDX_5F89F0923C688C67 (case_file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE solution_schema (id INT AUTO_INCREMENT NOT NULL, `schema` JSON NOT NULL, case_file_id INT NOT NULL, UNIQUE INDEX UNIQ_CB7896343C688C67 (case_file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
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
        $this->addSql('DROP TABLE case_file');
        $this->addSql('DROP TABLE document_metadata');
        $this->addSql('DROP TABLE evidence_document');
        $this->addSql('DROP TABLE solution_schema');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
