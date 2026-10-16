<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015094343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accept cross-document evidence for the posthumous signature finding.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_SET(s.solution_data, '$.expectedAnomalies[0].acceptedVariants', JSON_ARRAY(JSON_OBJECT('type', 'cross_document_inconsistency', 'minimumSelections', 2, 'minimumDistinctDocuments', 2, 'expectedEvidence', JSON_ARRAY(JSON_OBJECT('documentOrder', 1, 'selectionKeywords', JSON_ARRAY('18 septembre 1917')), JSON_OBJECT('documentOrder', 2, 'selectionKeywords', JSON_ARRAY('A. Varenne, 1923')))))) WHERE c.slug = 'le-port-silencieux'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_REMOVE(s.solution_data, '$.expectedAnomalies[0].acceptedVariants') WHERE c.slug = 'le-port-silencieux'");
    }
}
