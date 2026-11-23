<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123103537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date de dernière modification d\'un suivi, vide pour les suivis existants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webtoon_user ADD updated DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webtoon_user DROP updated');
    }
}
