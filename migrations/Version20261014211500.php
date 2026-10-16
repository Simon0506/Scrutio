<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014211500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete free-text justification scoring criteria from the pilot solution.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_REMOVE(s.solution_data, '$.expectedAnomalies[0].justificationAlternatives', '$.expectedAnomalies[0].justificationKeywords') WHERE c.slug = 'heritage-valabregue'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_SET(s.solution_data, '$.expectedAnomalies[0].justificationKeywords', JSON_ARRAY('codicille', '12 mars 2025', 'après le décès'), '$.expectedAnomalies[0].justificationAlternatives', JSON_ARRAY('date du codicille ne correspond pas', 'date du codicille est postérieure au décès', 'le codicille est daté après le décès', 'codicille daté après le décès', 'codicille établi après la mort', 'date portée au codicille incompatible avec le décès', 'incohérence chronologique entre le décès et le codicille', 'le défunt était déjà décédé à la date du codicille', 'la date est postérieure au décès')) WHERE c.slug = 'heritage-valabregue'");
    }
}
