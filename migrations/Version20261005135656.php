<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005135656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webtoon RENAME INDEX uniq_2801a79b18544fbf TO UNIQ_E0D10BFED3BD96C8');
        $this->addSql('ALTER TABLE webtoon_title RENAME INDEX idx_d2c54d69b2f2a108 TO IDX_CE4CD3D3CB3BA083');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webtoon RENAME INDEX uniq_e0d10bfed3bd96c8 TO UNIQ_2801A79B18544FBF');
        $this->addSql('ALTER TABLE webtoon_title RENAME INDEX idx_ce4cd3d3cb3ba083 TO IDX_D2C54D69B2F2A108');
    }
}
