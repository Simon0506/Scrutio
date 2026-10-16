<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014205707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pack pricing and persistent checkout purchases.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM content_pack') !== 1
                || (int) $this->connection->fetchOne("SELECT COUNT(*) FROM content_pack WHERE slug = 'les-dossiers-datelier'") !== 1,
            'Set explicit prices for existing packs before migrating.',
        );
        $this->addSql('CREATE TABLE pack_purchase (id INT AUTO_INCREMENT NOT NULL, amount_in_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, provider VARCHAR(16) NOT NULL, status VARCHAR(24) NOT NULL, provider_checkout_id VARCHAR(255) DEFAULT NULL, provider_payment_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, user_id INT NOT NULL, pack_id INT NOT NULL, UNIQUE INDEX uniq_pack_purchase_provider_checkout (provider_checkout_id), UNIQUE INDEX uniq_pack_purchase_provider_payment (provider_payment_id), INDEX IDX_4F98952AA76ED395 (user_id), INDEX IDX_4F98952A1919B217 (pack_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pack_purchase ADD CONSTRAINT FK_4F98952AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pack_purchase ADD CONSTRAINT FK_4F98952A1919B217 FOREIGN KEY (pack_id) REFERENCES content_pack (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE content_pack ADD price_amount_in_cents INT DEFAULT NULL, ADD currency VARCHAR(3) DEFAULT NULL');
        $this->addSql("UPDATE content_pack SET price_amount_in_cents = 499, currency = 'EUR' WHERE slug = 'les-dossiers-datelier'");
        $this->addSql('ALTER TABLE content_pack MODIFY price_amount_in_cents INT NOT NULL, MODIFY currency VARCHAR(3) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pack_purchase DROP FOREIGN KEY FK_4F98952AA76ED395');
        $this->addSql('ALTER TABLE pack_purchase DROP FOREIGN KEY FK_4F98952A1919B217');
        $this->addSql('DROP TABLE pack_purchase');
        $this->addSql('ALTER TABLE content_pack DROP price_amount_in_cents, DROP currency');
    }
}
