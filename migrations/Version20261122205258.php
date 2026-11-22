<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122205258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un webtoon sans couverture a image à NULL, et non plus defaut.jpg, un fichier qui n\'existe pas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webtoon CHANGE image image VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE webtoon SET image = NULL WHERE image = 'defaut.jpg'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE webtoon SET image = 'defaut.jpg' WHERE image IS NULL");
        $this->addSql('ALTER TABLE webtoon CHANGE image image VARCHAR(255) NOT NULL');
    }
}
