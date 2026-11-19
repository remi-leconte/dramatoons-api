<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les suivis vides (sans note, état ni chapitre, ou chapitre 0) et remplace l\'état vide par NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE webtoon_user SET state = NULL WHERE state = ''");
        $this->addSql('DELETE FROM webtoon_user WHERE rate IS NULL AND state IS NULL AND (bookmark IS NULL OR bookmark = 0)');
        // Un état vide comptait comme lecteur : le nombre de lecteurs est recalculé (même formule que WebtoonUserRepository::updateWebtoonStats)
        $this->addSql("UPDATE webtoon w SET readers_count = (
            SELECT COUNT(*) FROM webtoon_user wu
            WHERE wu.webtoon_id = w.id AND ((wu.state IS NOT NULL AND wu.state != 'break') OR wu.rate IS NOT NULL)
        )");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les suivis vides supprimés ne peuvent pas être recréés.');
    }
}
