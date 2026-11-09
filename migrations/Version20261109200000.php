<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recalcule le nombre de lecteurs : tout suivi dont l\'état n\'est pas « break »';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE webtoon w SET readers_count = (
            SELECT COUNT(*) FROM webtoon_user wu
            WHERE wu.webtoon_id = w.id AND (wu.state IS NULL OR wu.state != 'break')
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE webtoon w SET readers_count = (
            SELECT COUNT(*) FROM webtoon_user wu
            WHERE wu.webtoon_id = w.id AND ((wu.state IS NOT NULL AND wu.state != 'break') OR wu.rate IS NOT NULL)
        )");
    }
}
