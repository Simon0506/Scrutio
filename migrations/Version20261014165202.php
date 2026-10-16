<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014165202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store grouped evidence selections and require both temporal evidence items.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE player_evidence_selection (id INT AUTO_INCREMENT NOT NULL, selected_text LONGTEXT NOT NULL, position INT NOT NULL, anomaly_id INT NOT NULL, document_id INT NOT NULL, INDEX IDX_A0469AC6BAF977BB (anomaly_id), INDEX IDX_A0469AC6C33F7837 (document_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE player_evidence_selection ADD CONSTRAINT FK_A0469AC6BAF977BB FOREIGN KEY (anomaly_id) REFERENCES player_anomaly (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player_evidence_selection ADD CONSTRAINT FK_A0469AC6C33F7837 FOREIGN KEY (document_id) REFERENCES evidence_document (id)');
        $this->addSql('INSERT INTO player_evidence_selection (anomaly_id, document_id, selected_text, position) SELECT id, document_id, selected_text, 1 FROM player_anomaly');
        $this->addSql('ALTER TABLE player_anomaly DROP FOREIGN KEY `FK_980D8A26C33F7837`');
        $this->addSql('DROP INDEX IDX_980D8A26C33F7837 ON player_anomaly');
        $this->addSql('ALTER TABLE player_anomaly DROP selected_text, DROP document_id, CHANGE note justification LONGTEXT DEFAULT NULL');
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_REMOVE(JSON_SET(s.solution_data, '$.expectedAnomalies[0].minimumSelections', 2, '$.expectedAnomalies[0].minimumDistinctDocuments', 2, '$.expectedAnomalies[0].expectedEvidence', JSON_ARRAY(JSON_OBJECT('documentOrder', 1, 'selectionKeywords', JSON_ARRAY('4 mars 2025')), JSON_OBJECT('documentOrder', 3, 'selectionKeywords', JSON_ARRAY('12 mars 2025'))), '$.expectedAnomalies[0].justificationAlternatives', JSON_ARRAY('date du codicille ne correspond pas', 'date du codicille est postérieure au décès', 'codicille daté après le décès', 'codicille établi après la mort', 'date portée au codicille incompatible avec le décès', 'incohérence chronologique entre le décès et le codicille', 'le défunt était déjà décédé à la date du codicille', 'la date est postérieure au décès')), '$.expectedAnomalies[0].justificationKeywords') WHERE c.slug = 'heritage-valabregue'");
    }

    public function down(Schema $schema): void
    {
        $invalidRollbackCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT a.id FROM player_anomaly a LEFT JOIN player_evidence_selection e ON e.anomaly_id = a.id GROUP BY a.id HAVING COUNT(e.id) <> 1) AS non_single_evidence');
        $this->abortIf($invalidRollbackCount > 0, 'Cannot roll back grouped anomalies without losing evidence selections.');

        // Restore the single-evidence columns only when every anomaly has exactly one selection.
        $this->addSql('ALTER TABLE player_anomaly ADD selected_text LONGTEXT DEFAULT NULL, ADD document_id INT DEFAULT NULL, CHANGE justification note LONGTEXT DEFAULT NULL');
        $this->addSql('UPDATE player_anomaly a INNER JOIN player_evidence_selection e ON e.anomaly_id = a.id SET a.document_id = e.document_id, a.selected_text = e.selected_text');
        $this->addSql('ALTER TABLE player_anomaly MODIFY selected_text LONGTEXT NOT NULL, MODIFY document_id INT NOT NULL');
        $this->addSql('ALTER TABLE player_anomaly ADD CONSTRAINT `FK_980D8A26C33F7837` FOREIGN KEY (document_id) REFERENCES evidence_document (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_980D8A26C33F7837 ON player_anomaly (document_id)');
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_REMOVE(s.solution_data, '$.expectedAnomalies[0].minimumSelections', '$.expectedAnomalies[0].minimumDistinctDocuments', '$.expectedAnomalies[0].expectedEvidence', '$.expectedAnomalies[0].justificationAlternatives'), s.solution_data = JSON_SET(s.solution_data, '$.expectedAnomalies[0].justificationKeywords', JSON_ARRAY('codicille', '12 mars 2025', 'après le décès')) WHERE c.slug = 'heritage-valabregue'");
        $this->addSql('ALTER TABLE player_evidence_selection DROP FOREIGN KEY FK_A0469AC6BAF977BB');
        $this->addSql('ALTER TABLE player_evidence_selection DROP FOREIGN KEY FK_A0469AC6C33F7837');
        $this->addSql('DROP TABLE player_evidence_selection');
        $this->addSql('ALTER TABLE player_anomaly ADD selected_text LONGTEXT NOT NULL, ADD document_id INT NOT NULL, CHANGE justification note LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE player_anomaly ADD CONSTRAINT `FK_980D8A26C33F7837` FOREIGN KEY (document_id) REFERENCES evidence_document (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_980D8A26C33F7837 ON player_anomaly (document_id)');
    }
}
