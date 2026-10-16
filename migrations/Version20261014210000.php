<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accept the pilot justification wording about the codicil date being after death.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_ARRAY_APPEND(s.solution_data, '$.expectedAnomalies[0].justificationAlternatives', 'le codicille est daté après le décès') WHERE c.slug = 'heritage-valabregue' AND JSON_SEARCH(s.solution_data, 'one', 'le codicille est daté après le décès', NULL, '$.expectedAnomalies[0].justificationAlternatives') IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE solution_schema s INNER JOIN case_file c ON c.id = s.case_file_id SET s.solution_data = JSON_REMOVE(s.solution_data, JSON_UNQUOTE(JSON_SEARCH(s.solution_data, 'one', 'le codicille est daté après le décès', NULL, '$.expectedAnomalies[0].justificationAlternatives'))) WHERE c.slug = 'heritage-valabregue' AND JSON_SEARCH(s.solution_data, 'one', 'le codicille est daté après le décès', NULL, '$.expectedAnomalies[0].justificationAlternatives') IS NOT NULL");
    }
}
