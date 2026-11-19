<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115205426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime le slug des webtoons, qui ne servait plus qu\'au nom de la couverture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webtoon DROP slug');
    }

    public function down(Schema $schema): void
    {
        // Les slugs ne sont pas recalculés : la colonne revient vide
        $this->addSql('ALTER TABLE webtoon ADD slug VARCHAR(255) NOT NULL');
    }
}
