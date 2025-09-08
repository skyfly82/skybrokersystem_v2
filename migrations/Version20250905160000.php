<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250905160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new column to invoices to select new mPDF template';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE invoices ADD `new` TINYINT(1) NOT NULL DEFAULT 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE invoices DROP COLUMN `new`");
    }
}

