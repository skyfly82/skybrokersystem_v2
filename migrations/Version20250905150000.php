<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250905150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoices and invoice_items tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoices (
            id INT AUTO_INCREMENT NOT NULL,
            number VARCHAR(64) NOT NULL,
            issue_date DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            sell_date DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            payment_due_date DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            payment_method VARCHAR(32) NOT NULL,
            seller_name VARCHAR(200) NOT NULL,
            seller_address VARCHAR(300) NOT NULL,
            seller_nip VARCHAR(32) NOT NULL,
            seller_iban VARCHAR(100) DEFAULT NULL,
            seller_bank VARCHAR(100) DEFAULT NULL,
            buyer_name VARCHAR(200) NOT NULL,
            buyer_address VARCHAR(300) NOT NULL,
            buyer_nip VARCHAR(32) NOT NULL,
            paid_amount NUMERIC(12, 2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX idx_invoice_number (number)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE invoice_items (
            id INT AUTO_INCREMENT NOT NULL,
            invoice_id INT NOT NULL,
            lp INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) DEFAULT NULL,
            qty NUMERIC(12, 2) NOT NULL,
            jm VARCHAR(16) NOT NULL,
            vat INT NOT NULL,
            unit_brutto NUMERIC(12, 2) NOT NULL,
            total_brutto NUMERIC(12, 2) NOT NULL,
            INDEX idx_invoice_items_invoice_id (invoice_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE invoice_items ADD CONSTRAINT FK_invoice_items_invoice_id FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_items DROP FOREIGN KEY FK_invoice_items_invoice_id');
        $this->addSql('DROP TABLE invoice_items');
        $this->addSql('DROP TABLE invoices');
    }
}

