<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Wallabag\Doctrine\WallabagMigration;

/**
 * Add LDAP distinguished name on user table.
 */
final class Version20260217000100 extends WallabagMigration
{
    public function up(Schema $schema): void
    {
        $userTable = $schema->getTable($this->getTable('user'));

        $this->skipIf($userTable->hasColumn('dn'), 'It seems that you already played this migration.');

        $userTable->addColumn('dn', 'text', [
            'default' => null,
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $userTable = $schema->getTable($this->getTable('user'));

        if ($userTable->hasColumn('dn')) {
            $userTable->dropColumn('dn');
        }
    }
}
