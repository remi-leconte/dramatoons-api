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
        // des rôles (un seul) refuserait toute modification de ces comptes. Les rôles ont aussi pu être
        // enregistrés en objet JSON, d'où JSON_SEARCH : JSON_CONTAINS ne cherche que dans un tableau.
        // Son motif se lit comme un LIKE : _ est échappé, sinon ROLE-ADMIN deviendrait ROLE_ADMIN
        $this->addSql("UPDATE user SET roles = CASE
            WHEN JSON_SEARCH(roles, 'one', 'ROLE!_ADMIN', '!') IS NOT NULL THEN '[\"ROLE_ADMIN\"]'
            WHEN JSON_SEARCH(roles, 'one', 'ROLE!_MODO', '!') IS NOT NULL THEN '[\"ROLE_MODO\"]'
            ELSE '[\"ROLE_USER\"]'
        END
        WHERE roles NOT IN ('[\"ROLE_USER\"]', '[\"ROLE_MODO\"]', '[\"ROLE_ADMIN\"]')");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les rôles d\'origine ne sont pas conservés.');
    }
}
