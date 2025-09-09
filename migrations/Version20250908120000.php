<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create credit accounts and credit transactions tables';
    }

    public function up(Schema $schema): void
    {
        // Create v2_credit_accounts table
        $this->addSql('CREATE TABLE v2_credit_accounts (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            account_number VARCHAR(100) NOT NULL,
            account_type VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT \'pending_approval\' NOT NULL,
            credit_limit DECIMAL(10,2) NOT NULL,
            available_credit DECIMAL(10,2) NOT NULL,
            used_credit DECIMAL(10,2) DEFAULT \'0.00\' NOT NULL,
            overdraft_limit DECIMAL(10,2) DEFAULT \'0.00\' NOT NULL,
            payment_term_days SMALLINT DEFAULT 30 NOT NULL,
            currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL,
            interest_rate DECIMAL(5,2) DEFAULT NULL,
            overdraft_fee DECIMAL(5,2) DEFAULT NULL,
            last_credit_review_date DATE DEFAULT NULL,
            next_review_date DATE DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            suspended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_credit_account_status (status),
            INDEX idx_credit_account_type (account_type),
            INDEX idx_credit_account_created (created_at),
            UNIQUE INDEX UNIQ_CREDIT_ACCOUNT_NUMBER (account_number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create v2_credit_transactions table
        $this->addSql('CREATE TABLE v2_credit_transactions (
            id INT AUTO_INCREMENT NOT NULL,
            credit_account_id INT NOT NULL,
            payment_id INT DEFAULT NULL,
            transaction_id VARCHAR(100) NOT NULL,
            transaction_type VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT \'pending\' NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            due_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\',
            external_reference VARCHAR(255) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            authorized_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            settled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            failed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_credit_transaction_account (credit_account_id),
            INDEX idx_credit_transaction_payment (payment_id),
            INDEX idx_credit_transaction_type (transaction_type),
            INDEX idx_credit_transaction_status (status),
            INDEX idx_credit_transaction_created (created_at),
            INDEX idx_credit_transaction_due_date (due_date),
            UNIQUE INDEX UNIQ_CREDIT_TRANSACTION_ID (transaction_id),
            INDEX IDX_CREDIT_ACCOUNT (credit_account_id),
            INDEX IDX_PAYMENT (payment_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints - Note: user_id will reference either v2_customer_users or v2_system_users
        // For now, we'll skip the foreign key constraint until we implement proper user reference handling
        $this->addSql('ALTER TABLE v2_credit_transactions ADD CONSTRAINT FK_CREDIT_TRANSACTION_ACCOUNT FOREIGN KEY (credit_account_id) REFERENCES v2_credit_accounts (id)');
        $this->addSql('ALTER TABLE v2_credit_transactions ADD CONSTRAINT FK_CREDIT_TRANSACTION_PAYMENT FOREIGN KEY (payment_id) REFERENCES v2_payments (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE v2_credit_transactions DROP FOREIGN KEY FK_CREDIT_TRANSACTION_PAYMENT');
        $this->addSql('ALTER TABLE v2_credit_transactions DROP FOREIGN KEY FK_CREDIT_TRANSACTION_ACCOUNT');

        // Drop tables
        $this->addSql('DROP TABLE v2_credit_transactions');
        $this->addSql('DROP TABLE v2_credit_accounts');
    }
}