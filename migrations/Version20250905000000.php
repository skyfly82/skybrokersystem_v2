<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250905000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create secrets management tables';
    }

    public function up(Schema $schema): void
    {
        // Create secrets table
        $this->addSql('CREATE TABLE secrets (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            category VARCHAR(100) NOT NULL,
            name VARCHAR(100) NOT NULL,
            encrypted_value LONGTEXT NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            version INT DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            expires_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            last_accessed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            created_by VARCHAR(100) DEFAULT NULL,
            updated_by VARCHAR(100) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create indexes for secrets table
        $this->addSql('CREATE INDEX idx_secrets_category_name ON secrets (category, name)');
        $this->addSql('CREATE INDEX idx_secrets_category ON secrets (category)');
        $this->addSql('CREATE INDEX idx_secrets_active ON secrets (is_active)');

        // Create secret_audit_logs table
        $this->addSql('CREATE TABLE secret_audit_logs (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            secret_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            action VARCHAR(50) NOT NULL,
            user_identifier VARCHAR(100) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            performed_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            details LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create indexes for audit logs table
        $this->addSql('CREATE INDEX idx_audit_secret_id ON secret_audit_logs (secret_id)');
        $this->addSql('CREATE INDEX idx_audit_action ON secret_audit_logs (action)');
        $this->addSql('CREATE INDEX idx_audit_performed_at ON secret_audit_logs (performed_at)');
        $this->addSql('CREATE INDEX idx_audit_user ON secret_audit_logs (user_identifier)');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE secret_audit_logs ADD CONSTRAINT FK_audit_secret_id 
            FOREIGN KEY (secret_id) REFERENCES secrets (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key constraint first
        $this->addSql('ALTER TABLE secret_audit_logs DROP FOREIGN KEY FK_audit_secret_id');
        
        // Drop tables
        $this->addSql('DROP TABLE secret_audit_logs');
        $this->addSql('DROP TABLE secrets');
    }
}