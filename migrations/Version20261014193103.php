<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014193103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE player_profile (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(80) NOT NULL, reputation INT NOT NULL, cases_completed INT NOT NULL, cases_passed INT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_E0A3554AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE player_profile ADD CONSTRAINT FK_E0A3554AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investigation_session ADD player_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE investigation_session ADD CONSTRAINT FK_32C7D4899E6F5DF FOREIGN KEY (player_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_32C7D4899E6F5DF ON investigation_session (player_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player_profile DROP FOREIGN KEY FK_E0A3554AA76ED395');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE player_profile');
        $this->addSql('ALTER TABLE investigation_session DROP FOREIGN KEY FK_32C7D4899E6F5DF');
        $this->addSql('DROP INDEX IDX_32C7D4899E6F5DF ON investigation_session');
        $this->addSql('ALTER TABLE investigation_session DROP player_id');
    }
}
