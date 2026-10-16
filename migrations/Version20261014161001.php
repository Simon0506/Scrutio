<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014161001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist final audit submissions and configure pilot justification keywords.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE case_submission (id INT AUTO_INCREMENT NOT NULL, conclusion VARCHAR(32) NOT NULL, justification LONGTEXT NOT NULL, score INT NOT NULL, score_breakdown JSON NOT NULL, submitted_at DATETIME NOT NULL, investigation_session_id INT NOT NULL, UNIQUE INDEX UNIQ_F1445217B0817213 (investigation_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE case_submission ADD CONSTRAINT FK_F1445217B0817213 FOREIGN KEY (investigation_session_id) REFERENCES investigation_session (id) ON DELETE CASCADE');
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_SET(s.solution_data, '$.expectedAnomalies[0].justificationKeywords', JSON_ARRAY('codicille', '12 mars 2025', 'après le décès')) WHERE c.slug = 'heritage-valabregue'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_REMOVE(s.solution_data, '$.expectedAnomalies[0].justificationKeywords') WHERE c.slug = 'heritage-valabregue'");
        $this->addSql('ALTER TABLE case_submission DROP FOREIGN KEY FK_F1445217B0817213');
        $this->addSql('DROP TABLE case_submission');
    }
}
