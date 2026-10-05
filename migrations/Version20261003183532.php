<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003183532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table webtoon_title, migration des titres existants et liaison de la clé étrangère.';
    }

    public function up(Schema $schema): void
    {
        // 1. Création de la table webtoon_title
        $this->addSql('CREATE TABLE webtoon_title (
            id INT AUTO_INCREMENT NOT NULL, 
            webtoon_id INT NOT NULL, 
            title VARCHAR(255) NOT NULL, 
            INDEX IDX_D2C54D69B2F2A108 (webtoon_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // 2. Migration des données existantes : insertion de chaque titre dans webtoon_title
        $this->addSql('INSERT INTO webtoon_title (webtoon_id, title) SELECT id, title FROM webtoon WHERE title IS NOT NULL AND title != ""');

        // 3. Ajout de la colonne main_title_id sur la table webtoon
        $this->addSql('ALTER TABLE webtoon ADD main_title_id INT DEFAULT NULL');

        // 4. Association du titre principal dans webtoon à partir des enregistrements créés
        $this->addSql('UPDATE webtoon w INNER JOIN webtoon_title wt ON wt.webtoon_id = w.id SET w.main_title_id = wt.id');

        // 5. Contraintes de clés étrangères et suppression de la colonne devenue obsolète
        $this->addSql('ALTER TABLE webtoon_title ADD CONSTRAINT FK_D2C54D69B2F2A108 FOREIGN KEY (webtoon_id) REFERENCES webtoon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE webtoon ADD CONSTRAINT FK_2801A79B18544FBF FOREIGN KEY (main_title_id) REFERENCES webtoon_title (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2801A79B18544FBF ON webtoon (main_title_id)');
        $this->addSql('ALTER TABLE webtoon DROP COLUMN title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webtoon ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE webtoon w INNER JOIN webtoon_title wt ON w.main_title_id = wt.id SET w.title = wt.title');
        $this->addSql('ALTER TABLE webtoon DROP FOREIGN KEY FK_2801A79B18544FBF');
        $this->addSql('DROP INDEX UNIQ_2801A79B18544FBF ON webtoon');
        $this->addSql('ALTER TABLE webtoon DROP main_title_id');
        $this->addSql('ALTER TABLE webtoon_title DROP FOREIGN KEY FK_D2C54D69B2F2A108');
        $this->addSql('DROP TABLE webtoon_title');
    }
}