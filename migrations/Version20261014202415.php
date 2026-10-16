<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014202415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace season access with purchasable content packs.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM case_file WHERE is_free = 0') > 0,
            'Map existing paid cases to content packs before migrating.',
        );
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM season_access_grant') > 0,
            'Map existing season access grants to content packs before migrating.',
        );

        $this->addSql('CREATE TABLE content_pack (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, description LONGTEXT NOT NULL, display_order INT NOT NULL, UNIQUE INDEX UNIQ_5ABCB3E6989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pack_access_grant (id INT AUTO_INCREMENT NOT NULL, granted_at DATETIME NOT NULL, user_id INT NOT NULL, pack_id INT NOT NULL, UNIQUE INDEX uniq_pack_access_user_pack (user_id, pack_id), INDEX IDX_DF6B86B2A76ED395 (user_id), INDEX IDX_DF6B86B21919B217 (pack_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pack_access_grant ADD CONSTRAINT FK_DF6B86B2A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pack_access_grant ADD CONSTRAINT FK_DF6B86B21919B217 FOREIGN KEY (pack_id) REFERENCES content_pack (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE season_access_grant DROP FOREIGN KEY `FK_2403BE02A76ED395`');
        $this->addSql('DROP TABLE season_access_grant');
        $this->addSql('ALTER TABLE case_file ADD pack_id INT DEFAULT NULL, DROP season_number, DROP is_free');
        $this->addSql('ALTER TABLE case_file ADD CONSTRAINT FK_D87ECA31919B217 FOREIGN KEY (pack_id) REFERENCES content_pack (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_D87ECA31919B217 ON case_file (pack_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE season_access_grant (id INT AUTO_INCREMENT NOT NULL, season_number INT NOT NULL, granted_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_2403BE02A76ED395 (user_id), UNIQUE INDEX uniq_season_access_user_season (user_id, season_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE season_access_grant ADD CONSTRAINT `FK_2403BE02A76ED395` FOREIGN KEY (user_id) REFERENCES app_user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('INSERT INTO season_access_grant (season_number, granted_at, user_id) SELECT pack_id, granted_at, user_id FROM pack_access_grant');
        $this->addSql('ALTER TABLE case_file ADD season_number INT NOT NULL DEFAULT 1, ADD is_free TINYINT NOT NULL DEFAULT 1');
        $this->addSql('UPDATE case_file SET season_number = pack_id, is_free = 0 WHERE pack_id IS NOT NULL');
        $this->addSql('ALTER TABLE case_file DROP FOREIGN KEY FK_D87ECA31919B217');
        $this->addSql('DROP INDEX IDX_D87ECA31919B217 ON case_file');
        $this->addSql('ALTER TABLE case_file DROP pack_id');
        $this->addSql('ALTER TABLE pack_access_grant DROP FOREIGN KEY FK_DF6B86B2A76ED395');
        $this->addSql('ALTER TABLE pack_access_grant DROP FOREIGN KEY FK_DF6B86B21919B217');
        $this->addSql('DROP TABLE pack_access_grant');
        $this->addSql('DROP TABLE content_pack');
    }
}
