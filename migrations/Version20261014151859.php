<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014151859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE investigation_session (id INT AUTO_INCREMENT NOT NULL, public_token VARCHAR(48) NOT NULL, status VARCHAR(24) NOT NULL, started_at DATETIME NOT NULL, case_file_id INT NOT NULL, UNIQUE INDEX UNIQ_32C7D48AE981E3B (public_token), INDEX IDX_32C7D483C688C67 (case_file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE player_anomaly (id INT AUTO_INCREMENT NOT NULL, anomaly_type VARCHAR(48) NOT NULL, selected_text LONGTEXT NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, investigation_session_id INT NOT NULL, document_id INT NOT NULL, INDEX IDX_980D8A26B0817213 (investigation_session_id), INDEX IDX_980D8A26C33F7837 (document_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE investigation_session ADD CONSTRAINT FK_32C7D483C688C67 FOREIGN KEY (case_file_id) REFERENCES case_file (id)');
        $this->addSql('ALTER TABLE player_anomaly ADD CONSTRAINT FK_980D8A26B0817213 FOREIGN KEY (investigation_session_id) REFERENCES investigation_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player_anomaly ADD CONSTRAINT FK_980D8A26C33F7837 FOREIGN KEY (document_id) REFERENCES evidence_document (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE investigation_session DROP FOREIGN KEY FK_32C7D483C688C67');
        $this->addSql('ALTER TABLE player_anomaly DROP FOREIGN KEY FK_980D8A26B0817213');
        $this->addSql('ALTER TABLE player_anomaly DROP FOREIGN KEY FK_980D8A26C33F7837');
        $this->addSql('DROP TABLE investigation_session');
        $this->addSql('DROP TABLE player_anomaly');
    }
}
