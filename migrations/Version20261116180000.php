<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ramène les rôles de chaque compte à un seul rôle valide, le plus haut qu\'il avait';
    }

    public function up(Schema $schema): void
    {
        // Avant la v2.2.0, un compte était créé avec roles = [] : sans cette correction, la validation
        // des rôles (un seul) refuserait toute modification de ces comptes
        $this->addSql("UPDATE user SET roles = CASE
            WHEN JSON_CONTAINS(roles, '\"ROLE_ADMIN\"') THEN '[\"ROLE_ADMIN\"]'
            WHEN JSON_CONTAINS(roles, '\"ROLE_MODO\"') THEN '[\"ROLE_MODO\"]'
            ELSE '[\"ROLE_USER\"]'
        END
        WHERE roles NOT IN ('[\"ROLE_USER\"]', '[\"ROLE_MODO\"]', '[\"ROLE_ADMIN\"]')");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les rôles d\'origine ne sont pas conservés.');
    }
}
