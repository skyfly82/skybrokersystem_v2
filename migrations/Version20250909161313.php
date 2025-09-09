<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909161313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE secret_audit_logs CHANGE secret_id secret_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE v2_analytics_events CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE v2_carriers CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE v2_carriers RENAME INDEX uniq_carrier_code TO UNIQ_D697802277153098');
        $this->addSql('ALTER TABLE v2_cms_pages CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE v2_cms_pages RENAME INDEX uniq_cms_slug TO UNIQ_60B5A794989D9B62');
        $this->addSql('ALTER TABLE v2_credit_accounts CHANGE status status VARCHAR(50) NOT NULL, CHANGE used_credit used_credit NUMERIC(10, 2) NOT NULL, CHANGE overdraft_limit overdraft_limit NUMERIC(10, 2) NOT NULL, CHANGE payment_term_days payment_term_days SMALLINT NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE last_credit_review_date last_credit_review_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE next_review_date next_review_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE v2_credit_accounts ADD CONSTRAINT FK_BD8257EBA76ED395 FOREIGN KEY (user_id) REFERENCES v2_system_users (id)');
        $this->addSql('CREATE INDEX IDX_BD8257EBA76ED395 ON v2_credit_accounts (user_id)');
        $this->addSql('ALTER TABLE v2_credit_accounts RENAME INDEX uniq_credit_account_number TO UNIQ_BD8257EBB1A4D127');
        $this->addSql('DROP INDEX IDX_CREDIT_ACCOUNT ON v2_credit_transactions');
        $this->addSql('DROP INDEX IDX_PAYMENT ON v2_credit_transactions');
        $this->addSql('ALTER TABLE v2_credit_transactions CHANGE status status VARCHAR(50) NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE v2_credit_transactions ADD CONSTRAINT FK_B6E294174C3A3BB FOREIGN KEY (payment_id) REFERENCES v2_payments (id)');
        $this->addSql('ALTER TABLE v2_credit_transactions RENAME INDEX uniq_credit_transaction_id TO UNIQ_B6E294172FC0CB0F');
        $this->addSql('ALTER TABLE v2_customer_users DROP FOREIGN KEY FK_V2_CUSTOMER_USER_CUSTOMER');
        $this->addSql('ALTER TABLE v2_customer_users CHANGE customer_role customer_role VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE v2_customer_users ADD CONSTRAINT FK_ADCB66FC9395C3F3 FOREIGN KEY (customer_id) REFERENCES v2_customers (id)');
        $this->addSql('ALTER TABLE v2_customer_users RENAME INDEX idx_customer_user_customer TO IDX_ADCB66FC9395C3F3');
        $this->addSql('ALTER TABLE v2_customers CHANGE type type VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE v2_invitations DROP FOREIGN KEY FK_V2_INVITATION_ACCEPTED_BY');
        $this->addSql('ALTER TABLE v2_invitations DROP FOREIGN KEY FK_V2_INVITATION_CUSTOMER');
        $this->addSql('ALTER TABLE v2_invitations DROP FOREIGN KEY FK_V2_INVITATION_INVITED_BY');
        $this->addSql('DROP INDEX IDX_INVITATION_EMAIL ON v2_invitations');
        $this->addSql('DROP INDEX IDX_INVITATION_STATUS ON v2_invitations');
        $this->addSql('ALTER TABLE v2_invitations CHANGE customer_role customer_role VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE v2_invitations ADD CONSTRAINT FK_A27CBD6F9395C3F3 FOREIGN KEY (customer_id) REFERENCES v2_customers (id)');
        $this->addSql('ALTER TABLE v2_invitations ADD CONSTRAINT FK_A27CBD6FA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES v2_customer_users (id)');
        $this->addSql('ALTER TABLE v2_invitations ADD CONSTRAINT FK_A27CBD6F20F699D9 FOREIGN KEY (accepted_by_id) REFERENCES v2_customer_users (id)');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX uniq_invitation_token TO UNIQ_A27CBD6F5F37A13B');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX idx_invitation_customer TO IDX_A27CBD6F9395C3F3');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX idx_invitation_invited_by TO IDX_A27CBD6FA7B4A7E3');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX idx_invitation_accepted_by TO IDX_A27CBD6F20F699D9');
        $this->addSql('ALTER TABLE v2_payments CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE v2_payments ADD CONSTRAINT FK_BC0DB1A5A76ED395 FOREIGN KEY (user_id) REFERENCES v2_system_users (id)');
        $this->addSql('ALTER TABLE v2_payments RENAME INDEX uniq_payment_id TO UNIQ_BC0DB1A54C3A3BB');
        $this->addSql('ALTER TABLE v2_payments RENAME INDEX idx_payment_user TO IDX_BC0DB1A5A76ED395');
        $this->addSql('ALTER TABLE v2_preliminary_registrations CHANGE country country VARCHAR(2) DEFAULT NULL, CHANGE email_verified email_verified TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE v2_preliminary_registrations RENAME INDEX uniq_v2_preliminary_registrations_token TO UNIQ_F52A94C55F37A13B');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP FOREIGN KEY v2_pricing_tables_ibfk_1');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP FOREIGN KEY v2_pricing_tables_ibfk_2');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, ADD pricing_model VARCHAR(20) NOT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD min_weight_kg NUMERIC(8, 3) NOT NULL, ADD max_weight_kg NUMERIC(8, 3) DEFAULT NULL, ADD min_dimensions_cm JSON DEFAULT NULL, ADD max_dimensions_cm JSON DEFAULT NULL, ADD volumetric_divisor INT DEFAULT NULL, ADD tax_rate NUMERIC(5, 2) DEFAULT NULL, ADD config JSON DEFAULT NULL, ADD effective_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD effective_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE service_type service_type VARCHAR(50) NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD CONSTRAINT FK_69A9F53121DFC797 FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id)');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD CONSTRAINT FK_69A9F5319F2C3FAB FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id)');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD CONSTRAINT FK_69A9F531B03A8386 FOREIGN KEY (created_by_id) REFERENCES v2_system_users (id)');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD CONSTRAINT FK_69A9F531896DBBDE FOREIGN KEY (updated_by_id) REFERENCES v2_system_users (id)');
        $this->addSql('CREATE INDEX IDX_69A9F531B03A8386 ON v2_pricing_tables (created_by_id)');
        $this->addSql('CREATE INDEX IDX_69A9F531896DBBDE ON v2_pricing_tables (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_PRICING_CARRIER_ZONE ON v2_pricing_tables (carrier_id, zone_id)');
        $this->addSql('CREATE INDEX IDX_PRICING_ACTIVE ON v2_pricing_tables (is_active)');
        $this->addSql('CREATE INDEX IDX_PRICING_EFFECTIVE ON v2_pricing_tables (effective_from, effective_until)');
        $this->addSql('CREATE INDEX IDX_PRICING_SERVICE ON v2_pricing_tables (service_type)');
        $this->addSql('CREATE UNIQUE INDEX UNQ_PRICING_CARRIER_ZONE_SERVICE_VERSION ON v2_pricing_tables (carrier_id, zone_id, service_type, version)');
        $this->addSql('ALTER TABLE v2_pricing_tables RENAME INDEX carrier_id TO IDX_69A9F53121DFC797');
        $this->addSql('ALTER TABLE v2_pricing_tables RENAME INDEX zone_id TO IDX_69A9F5319F2C3FAB');
        $this->addSql('ALTER TABLE v2_pricing_zones CHANGE code code VARCHAR(10) NOT NULL, CHANGE name name VARCHAR(100) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_ZONE_TYPE ON v2_pricing_zones (zone_type)');
        $this->addSql('CREATE INDEX IDX_ZONE_ACTIVE ON v2_pricing_zones (is_active)');
        $this->addSql('CREATE INDEX IDX_ZONE_SORT ON v2_pricing_zones (sort_order)');
        $this->addSql('ALTER TABLE v2_pricing_zones RENAME INDEX uniq_zone_code TO UNIQ_D3BE81E877153098');
        $this->addSql('ALTER TABLE v2_system_users CHANGE department department VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
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
        $this->addSql('ALTER TABLE secret_audit_logs CHANGE secret_id secret_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE v2_analytics_events CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE v2_carriers CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE v2_carriers RENAME INDEX uniq_d697802277153098 TO UNIQ_CARRIER_CODE');
        $this->addSql('ALTER TABLE v2_cms_pages CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE v2_cms_pages RENAME INDEX uniq_60b5a794989d9b62 TO UNIQ_CMS_SLUG');
        $this->addSql('ALTER TABLE v2_credit_accounts DROP FOREIGN KEY FK_BD8257EBA76ED395');
        $this->addSql('DROP INDEX IDX_BD8257EBA76ED395 ON v2_credit_accounts');
        $this->addSql('ALTER TABLE v2_credit_accounts CHANGE status status VARCHAR(50) DEFAULT \'pending_approval\' NOT NULL, CHANGE used_credit used_credit NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, CHANGE overdraft_limit overdraft_limit NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, CHANGE payment_term_days payment_term_days SMALLINT DEFAULT 30 NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL, CHANGE last_credit_review_date last_credit_review_date DATE DEFAULT NULL, CHANGE next_review_date next_review_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE v2_credit_accounts RENAME INDEX uniq_bd8257ebb1a4d127 TO UNIQ_CREDIT_ACCOUNT_NUMBER');
        $this->addSql('ALTER TABLE v2_credit_transactions DROP FOREIGN KEY FK_B6E294174C3A3BB');
        $this->addSql('ALTER TABLE v2_credit_transactions CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL');
        $this->addSql('CREATE INDEX IDX_CREDIT_ACCOUNT ON v2_credit_transactions (credit_account_id)');
        $this->addSql('CREATE INDEX IDX_PAYMENT ON v2_credit_transactions (payment_id)');
        $this->addSql('ALTER TABLE v2_credit_transactions RENAME INDEX uniq_b6e294172fc0cb0f TO UNIQ_CREDIT_TRANSACTION_ID');
        $this->addSql('ALTER TABLE v2_customer_users DROP FOREIGN KEY FK_ADCB66FC9395C3F3');
        $this->addSql('ALTER TABLE v2_customer_users CHANGE customer_role customer_role VARCHAR(50) DEFAULT \'employee\' NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE v2_customer_users ADD CONSTRAINT FK_V2_CUSTOMER_USER_CUSTOMER FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE v2_customer_users RENAME INDEX idx_adcb66fc9395c3f3 TO IDX_CUSTOMER_USER_CUSTOMER');
        $this->addSql('ALTER TABLE v2_customers CHANGE type type VARCHAR(50) DEFAULT \'business\' NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE v2_invitations DROP FOREIGN KEY FK_A27CBD6F9395C3F3');
        $this->addSql('ALTER TABLE v2_invitations DROP FOREIGN KEY FK_A27CBD6FA7B4A7E3');
        $this->addSql('ALTER TABLE v2_invitations DROP FOREIGN KEY FK_A27CBD6F20F699D9');
        $this->addSql('ALTER TABLE v2_invitations CHANGE customer_role customer_role VARCHAR(50) DEFAULT \'employee\' NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE v2_invitations ADD CONSTRAINT FK_V2_INVITATION_ACCEPTED_BY FOREIGN KEY (accepted_by_id) REFERENCES v2_customer_users (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE v2_invitations ADD CONSTRAINT FK_V2_INVITATION_CUSTOMER FOREIGN KEY (customer_id) REFERENCES v2_customers (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE v2_invitations ADD CONSTRAINT FK_V2_INVITATION_INVITED_BY FOREIGN KEY (invited_by_id) REFERENCES v2_customer_users (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_INVITATION_EMAIL ON v2_invitations (email)');
        $this->addSql('CREATE INDEX IDX_INVITATION_STATUS ON v2_invitations (status)');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX uniq_a27cbd6f5f37a13b TO UNIQ_INVITATION_TOKEN');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX idx_a27cbd6f9395c3f3 TO IDX_INVITATION_CUSTOMER');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX idx_a27cbd6fa7b4a7e3 TO IDX_INVITATION_INVITED_BY');
        $this->addSql('ALTER TABLE v2_invitations RENAME INDEX idx_a27cbd6f20f699d9 TO IDX_INVITATION_ACCEPTED_BY');
        $this->addSql('ALTER TABLE v2_payments DROP FOREIGN KEY FK_BC0DB1A5A76ED395');
        $this->addSql('ALTER TABLE v2_payments CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE v2_payments RENAME INDEX uniq_bc0db1a54c3a3bb TO UNIQ_PAYMENT_ID');
        $this->addSql('ALTER TABLE v2_payments RENAME INDEX idx_bc0db1a5a76ed395 TO IDX_PAYMENT_USER');
        $this->addSql('ALTER TABLE v2_preliminary_registrations CHANGE country country CHAR(2) DEFAULT NULL, CHANGE email_verified email_verified TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE v2_preliminary_registrations RENAME INDEX uniq_f52a94c55f37a13b TO UNIQ_V2_PRELIMINARY_REGISTRATIONS_TOKEN');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP FOREIGN KEY FK_69A9F53121DFC797');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP FOREIGN KEY FK_69A9F5319F2C3FAB');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP FOREIGN KEY FK_69A9F531B03A8386');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP FOREIGN KEY FK_69A9F531896DBBDE');
        $this->addSql('DROP INDEX IDX_69A9F531B03A8386 ON v2_pricing_tables');
        $this->addSql('DROP INDEX IDX_69A9F531896DBBDE ON v2_pricing_tables');
        $this->addSql('DROP INDEX IDX_PRICING_CARRIER_ZONE ON v2_pricing_tables');
        $this->addSql('DROP INDEX IDX_PRICING_ACTIVE ON v2_pricing_tables');
        $this->addSql('DROP INDEX IDX_PRICING_EFFECTIVE ON v2_pricing_tables');
        $this->addSql('DROP INDEX IDX_PRICING_SERVICE ON v2_pricing_tables');
        $this->addSql('DROP INDEX UNQ_PRICING_CARRIER_ZONE_SERVICE_VERSION ON v2_pricing_tables');
        $this->addSql('ALTER TABLE v2_pricing_tables DROP created_by_id, DROP updated_by_id, DROP pricing_model, DROP version, DROP description, DROP min_weight_kg, DROP max_weight_kg, DROP min_dimensions_cm, DROP max_dimensions_cm, DROP volumetric_divisor, DROP tax_rate, DROP config, DROP effective_from, DROP effective_until, DROP updated_at, CHANGE service_type service_type VARCHAR(50) DEFAULT \'standard\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD CONSTRAINT v2_pricing_tables_ibfk_1 FOREIGN KEY (carrier_id) REFERENCES v2_carriers (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE v2_pricing_tables ADD CONSTRAINT v2_pricing_tables_ibfk_2 FOREIGN KEY (zone_id) REFERENCES v2_pricing_zones (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE v2_pricing_tables RENAME INDEX idx_69a9f53121dfc797 TO carrier_id');
        $this->addSql('ALTER TABLE v2_pricing_tables RENAME INDEX idx_69a9f5319f2c3fab TO zone_id');
        $this->addSql('DROP INDEX IDX_ZONE_TYPE ON v2_pricing_zones');
        $this->addSql('DROP INDEX IDX_ZONE_ACTIVE ON v2_pricing_zones');
        $this->addSql('DROP INDEX IDX_ZONE_SORT ON v2_pricing_zones');
        $this->addSql('ALTER TABLE v2_pricing_zones CHANGE code code VARCHAR(10) NOT NULL COMMENT \'Unique zone code\', CHANGE name name VARCHAR(100) NOT NULL COMMENT \'Zone name\', CHANGE description description TEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE v2_pricing_zones RENAME INDEX uniq_d3be81e877153098 TO UNIQ_ZONE_CODE');
        $this->addSql('ALTER TABLE v2_system_users CHANGE department department VARCHAR(50) DEFAULT \'support\' NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_top_ups CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_top_ups RENAME INDEX uniq_a420090190308d68 TO UNIQ_WALLET_TOP_UP_ID');
        $this->addSql('ALTER TABLE v2_wallet_top_ups RENAME INDEX idx_a4200901924c1837 TO idx_wallet_top_up_transaction');
        $this->addSql('ALTER TABLE v2_wallet_transactions CHANGE status status VARCHAR(50) DEFAULT \'pending\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL');
        $this->addSql('ALTER TABLE v2_wallet_transactions RENAME INDEX uniq_dfbd91f32fc0cb0f TO UNIQ_WALLET_TRANSACTION_ID');
        $this->addSql('ALTER TABLE v2_wallets DROP FOREIGN KEY FK_9F0AE185A76ED395');
        $this->addSql('ALTER TABLE v2_wallets CHANGE status status VARCHAR(50) DEFAULT \'active\' NOT NULL, CHANGE balance balance NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE reserved_balance reserved_balance NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE available_balance available_balance NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT \'PLN\' NOT NULL, CHANGE low_balance_threshold low_balance_threshold NUMERIC(15, 2) DEFAULT \'10.00\' NOT NULL, CHANGE low_balance_notification_sent low_balance_notification_sent TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE v2_wallets RENAME INDEX uniq_9f0ae18526308010 TO UNIQ_WALLET_NUMBER');
        $this->addSql('ALTER TABLE v2_wallets RENAME INDEX idx_9f0ae185a76ed395 TO IDX_WALLET_USER');
    }
}
