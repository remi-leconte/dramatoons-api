<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260819155834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, login VARCHAR(255) NOT NULL, created DATETIME NOT NULL, updated DATETIME NOT NULL, resetTokenExpiration DATETIME DEFAULT NULL, resetToken VARCHAR(255) DEFAULT NULL, searchSortBy VARCHAR(255) DEFAULT NULL, searchSortOrder VARCHAR(255) DEFAULT NULL, searchStatus JSON DEFAULT NULL, searchItemsPerPage INT DEFAULT NULL, rememberToken VARCHAR(64) DEFAULT NULL, publish TINYINT DEFAULT 1, verified TINYINT DEFAULT 0, UNIQUE INDEX UNIQ_8D93D649AA08CB10 (login), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE webtoon (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, status VARCHAR(255) DEFAULT NULL, chapter INT NOT NULL, created DATETIME NOT NULL, updated DATETIME NOT NULL, publish TINYINT NOT NULL, image VARCHAR(255) NOT NULL, comment VARCHAR(255) DEFAULT NULL, lastVerification DATE DEFAULT NULL, average_rating DOUBLE PRECISION DEFAULT NULL, readers_count INT NOT NULL, user_id INT NOT NULL, INDEX IDX_E0D10BFEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE webtoon_user (id INT AUTO_INCREMENT NOT NULL, rate DOUBLE PRECISION DEFAULT NULL, state VARCHAR(255) DEFAULT NULL, bookmark INT DEFAULT NULL, user_id INT NOT NULL, webtoon_id INT NOT NULL, INDEX IDX_22866384A76ED395 (user_id), INDEX IDX_22866384CB3BA083 (webtoon_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE webtoon ADD CONSTRAINT FK_E0D10BFEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE webtoon_user ADD CONSTRAINT FK_22866384A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE webtoon_user ADD CONSTRAINT FK_22866384CB3BA083 FOREIGN KEY (webtoon_id) REFERENCES webtoon (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webtoon DROP FOREIGN KEY FK_E0D10BFEA76ED395');
        $this->addSql('ALTER TABLE webtoon_user DROP FOREIGN KEY FK_22866384A76ED395');
        $this->addSql('ALTER TABLE webtoon_user DROP FOREIGN KEY FK_22866384CB3BA083');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE webtoon');
        $this->addSql('DROP TABLE webtoon_user');
    }
}
