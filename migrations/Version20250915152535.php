<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915152535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE secret_audit_logs CHANGE secret_id secret_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE v2_pricing_tables RENAME INDEX fk_69a9f5319f2c3fab TO IDX_69A9F5319F2C3FAB');
        $this->addSql('ALTER TABLE v2_pricing_zones CHANGE code code VARCHAR(10) NOT NULL, CHANGE name name VARCHAR(100) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_ZONE_TYPE ON v2_pricing_zones (zone_type)');
        $this->addSql('CREATE INDEX IDX_ZONE_ACTIVE ON v2_pricing_zones (is_active)');
        $this->addSql('CREATE INDEX IDX_ZONE_SORT ON v2_pricing_zones (sort_order)');
        $this->addSql('ALTER TABLE v2_pricing_zones RENAME INDEX uniq_zone_code TO UNIQ_D3BE81E877153098');
        $this->addSql('ALTER TABLE v2_wallet_top_ups CHANGE status status VARCHAR(50) NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_top_ups RENAME INDEX uniq_wallet_top_up_id TO UNIQ_A420090190308D68');
        $this->addSql('ALTER TABLE v2_wallet_top_ups RENAME INDEX idx_wallet_top_up_transaction TO IDX_A4200901924C1837');
        $this->addSql('ALTER TABLE v2_wallet_transactions CHANGE status status VARCHAR(50) NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_transactions RENAME INDEX uniq_wallet_transaction_id TO UNIQ_DFBD91F32FC0CB0F');
        $this->addSql('ALTER TABLE v2_wallets CHANGE status status VARCHAR(50) NOT NULL, CHANGE balance balance NUMERIC(15, 2) NOT NULL, CHANGE reserved_balance reserved_balance NUMERIC(15, 2) NOT NULL, CHANGE available_balance available_balance NUMERIC(15, 2) NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE low_balance_threshold low_balance_threshold NUMERIC(15, 2) NOT NULL, CHANGE low_balance_notification_sent low_balance_notification_sent TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE v2_wallets ADD CONSTRAINT FK_9F0AE185A76ED395 FOREIGN KEY (user_id) REFERENCES v2_system_users (id)');
        $this->addSql('ALTER TABLE v2_wallets RENAME INDEX uniq_wallet_number TO UNIQ_9F0AE18526308010');
        $this->addSql('ALTER TABLE v2_wallets RENAME INDEX idx_wallet_user TO IDX_9F0AE185A76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE v2_wallets DROP FOREIGN KEY FK_9F0AE185A76ED395');
        $this->addSql('ALTER TABLE v2_wallets CHANGE status status VARCHAR(50) DEFAULT \'active\' NOT NULL, CHANGE balance balance NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE reserved_balance reserved_balance NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE available_balance available_balance NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL, CHANGE low_balance_threshold low_balance_threshold NUMERIC(15, 2) DEFAULT \'10.00\' NOT NULL, CHANGE low_balance_notification_sent low_balance_notification_sent TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE v2_wallets RENAME INDEX uniq_9f0ae18526308010 TO UNIQ_WALLET_NUMBER');
        $this->addSql('ALTER TABLE v2_wallets RENAME INDEX idx_9f0ae185a76ed395 TO IDX_WALLET_USER');
        $this->addSql('ALTER TABLE v2_wallet_transactions CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_transactions RENAME INDEX uniq_dfbd91f32fc0cb0f TO UNIQ_WALLET_TRANSACTION_ID');
        $this->addSql('ALTER TABLE v2_wallet_top_ups CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_top_ups RENAME INDEX uniq_a420090190308d68 TO UNIQ_WALLET_TOP_UP_ID');
        $this->addSql('ALTER TABLE v2_wallet_top_ups RENAME INDEX idx_a4200901924c1837 TO idx_wallet_top_up_transaction');
        $this->addSql('DROP INDEX IDX_ZONE_TYPE ON v2_pricing_zones');
        $this->addSql('DROP INDEX IDX_ZONE_ACTIVE ON v2_pricing_zones');
        $this->addSql('DROP INDEX IDX_ZONE_SORT ON v2_pricing_zones');
        $this->addSql('ALTER TABLE v2_pricing_zones CHANGE code code VARCHAR(10) NOT NULL COMMENT \'Unique zone code\', CHANGE name name VARCHAR(100) NOT NULL COMMENT \'Zone name\', CHANGE description description TEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE v2_pricing_zones RENAME INDEX uniq_d3be81e877153098 TO UNIQ_ZONE_CODE');
        $this->addSql('ALTER TABLE v2_pricing_tables RENAME INDEX idx_69a9f5319f2c3fab TO FK_69A9F5319F2C3FAB');
        $this->addSql('ALTER TABLE secret_audit_logs CHANGE secret_id secret_id CHAR(36) DEFAULT NULL');
    }
}
