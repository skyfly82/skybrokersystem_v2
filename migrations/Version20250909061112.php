<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909061112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payment system tables: v2_payments, v2_wallets, v2_wallet_transactions, v2_wallet_top_ups';
    }

    public function up(Schema $schema): void
    {
        // Note: v2_payments and v2_wallets tables already exist, only creating missing tables

        // Create v2_wallet_transactions table
        $this->addSql('CREATE TABLE v2_wallet_transactions (
            id INT AUTO_INCREMENT NOT NULL,
            wallet_id INT NOT NULL,
            payment_id INT DEFAULT NULL,
            source_wallet_id INT DEFAULT NULL,
            destination_wallet_id INT DEFAULT NULL,
            transaction_id VARCHAR(100) NOT NULL,
            transaction_type VARCHAR(50) NOT NULL,
            category VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT \'pending\' NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL,
            fee_amount DECIMAL(15,2) DEFAULT NULL,
            balance_before DECIMAL(15,2) DEFAULT NULL,
            balance_after DECIMAL(15,2) DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            external_reference VARCHAR(255) DEFAULT NULL,
            original_transaction_id VARCHAR(255) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            failed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_wallet_transaction_wallet (wallet_id),
            INDEX idx_wallet_transaction_payment (payment_id),
            INDEX idx_wallet_transaction_type (transaction_type),
            INDEX idx_wallet_transaction_status (status),
            INDEX idx_wallet_transaction_created (created_at),
            INDEX idx_wallet_transaction_source (source_wallet_id),
            INDEX idx_wallet_transaction_destination (destination_wallet_id),
            UNIQUE INDEX UNIQ_WALLET_TRANSACTION_ID (transaction_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create v2_wallet_top_ups table
        $this->addSql('CREATE TABLE v2_wallet_top_ups (
            id INT AUTO_INCREMENT NOT NULL,
            wallet_id INT NOT NULL,
            payment_id INT DEFAULT NULL,
            wallet_transaction_id INT DEFAULT NULL,
            top_up_id VARCHAR(100) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT \'pending\' NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            fee_amount DECIMAL(10,2) DEFAULT NULL,
            net_amount DECIMAL(10,2) DEFAULT NULL,
            currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            external_transaction_id VARCHAR(255) DEFAULT NULL,
            payment_url VARCHAR(500) DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            payment_gateway_data JSON DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            failed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_wallet_top_up_wallet (wallet_id),
            INDEX idx_wallet_top_up_payment (payment_id),
            INDEX idx_wallet_top_up_status (status),
            INDEX idx_wallet_top_up_method (payment_method),
            INDEX idx_wallet_top_up_created (created_at),
            INDEX idx_wallet_top_up_transaction (wallet_transaction_id),
            UNIQUE INDEX UNIQ_WALLET_TOP_UP_ID (top_up_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE v2_wallet_transactions ADD CONSTRAINT FK_WALLET_TRANSACTION_WALLET FOREIGN KEY (wallet_id) REFERENCES v2_wallets (id)');
        $this->addSql('ALTER TABLE v2_wallet_transactions ADD CONSTRAINT FK_WALLET_TRANSACTION_PAYMENT FOREIGN KEY (payment_id) REFERENCES v2_payments (id)');
        $this->addSql('ALTER TABLE v2_wallet_transactions ADD CONSTRAINT FK_WALLET_TRANSACTION_SOURCE FOREIGN KEY (source_wallet_id) REFERENCES v2_wallets (id)');
        $this->addSql('ALTER TABLE v2_wallet_transactions ADD CONSTRAINT FK_WALLET_TRANSACTION_DESTINATION FOREIGN KEY (destination_wallet_id) REFERENCES v2_wallets (id)');
        
        $this->addSql('ALTER TABLE v2_wallet_top_ups ADD CONSTRAINT FK_WALLET_TOP_UP_WALLET FOREIGN KEY (wallet_id) REFERENCES v2_wallets (id)');
        $this->addSql('ALTER TABLE v2_wallet_top_ups ADD CONSTRAINT FK_WALLET_TOP_UP_PAYMENT FOREIGN KEY (payment_id) REFERENCES v2_payments (id)');
        $this->addSql('ALTER TABLE v2_wallet_top_ups ADD CONSTRAINT FK_WALLET_TOP_UP_TRANSACTION FOREIGN KEY (wallet_transaction_id) REFERENCES v2_wallet_transactions (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE v2_wallet_top_ups DROP FOREIGN KEY FK_WALLET_TOP_UP_TRANSACTION');
        $this->addSql('ALTER TABLE v2_wallet_top_ups DROP FOREIGN KEY FK_WALLET_TOP_UP_PAYMENT');
        $this->addSql('ALTER TABLE v2_wallet_top_ups DROP FOREIGN KEY FK_WALLET_TOP_UP_WALLET');
        
        $this->addSql('ALTER TABLE v2_wallet_transactions DROP FOREIGN KEY FK_WALLET_TRANSACTION_DESTINATION');
        $this->addSql('ALTER TABLE v2_wallet_transactions DROP FOREIGN KEY FK_WALLET_TRANSACTION_SOURCE');
        $this->addSql('ALTER TABLE v2_wallet_transactions DROP FOREIGN KEY FK_WALLET_TRANSACTION_PAYMENT');
        $this->addSql('ALTER TABLE v2_wallet_transactions DROP FOREIGN KEY FK_WALLET_TRANSACTION_WALLET');

        // Drop only the tables we created (v2_payments and v2_wallets already existed)
        $this->addSql('DROP TABLE v2_wallet_top_ups');
        $this->addSql('DROP TABLE v2_wallet_transactions');
    }
}
