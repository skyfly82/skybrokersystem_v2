<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to convert separate user tables to Single Table Inheritance
 */
final class Version20250909140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert separate user tables (v2_system_users, v2_customer_users) to Single Table Inheritance (v2_users)';
    }

    public function up(Schema $schema): void
    {
        // Create unified v2_users table with STI discriminator
        $this->addSql('CREATE TABLE v2_users (
            id INT AUTO_INCREMENT NOT NULL,
            user_type VARCHAR(255) NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'active\',
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            email_verified_at DATETIME DEFAULT NULL,
            department VARCHAR(50) DEFAULT NULL,
            position VARCHAR(100) DEFAULT NULL,
            customer_role VARCHAR(50) DEFAULT NULL,
            customer_id INT DEFAULT NULL,
            UNIQUE INDEX UNIQ_USER_EMAIL (email),
            INDEX IDX_DISCRIMINATOR (user_type),
            INDEX idx_customer_relation (customer_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Migrate data from v2_system_users to v2_users with discriminator
        $this->addSql("
            INSERT INTO v2_users (
                user_type, email, roles, password, first_name, last_name, phone, status,
                created_at, updated_at, last_login_at, email_verified_at,
                department, position
            )
            SELECT 
                'system' as user_type,
                email, roles, password, first_name, last_name, phone, status,
                created_at, updated_at, last_login_at, email_verified_at,
                department, position
            FROM v2_system_users
            WHERE email IS NOT NULL
        ");

        // Migrate data from v2_customer_users to v2_users with discriminator
        $this->addSql("
            INSERT INTO v2_users (
                user_type, email, roles, password, first_name, last_name, phone, status,
                created_at, updated_at, last_login_at, email_verified_at,
                customer_role, customer_id
            )
            SELECT 
                'customer' as user_type,
                email, roles, password, first_name, last_name, phone, status,
                created_at, updated_at, last_login_at, email_verified_at,
                customer_role, customer_id
            FROM v2_customer_users
            WHERE email IS NOT NULL
        ");

        // Update payment system tables to reference unified user table
        // Update v2_payments table
        if ($schema->hasTable('v2_payments')) {
            $this->addSql('ALTER TABLE v2_payments ADD COLUMN temp_user_id INT DEFAULT NULL');
            
            // Update payments for system users
            $this->addSql("
                UPDATE v2_payments p 
                JOIN v2_users u ON p.user_id = u.id 
                SET p.temp_user_id = u.id 
                WHERE u.user_type = 'system'
            ");
            
            // Update payments for customer users (if any exist with different IDs)
            $this->addSql("
                UPDATE v2_payments p 
                JOIN v2_users u ON p.user_id = (
                    SELECT cu.id 
                    FROM v2_customer_users cu 
                    WHERE cu.email = u.email 
                    AND u.user_type = 'customer'
                    LIMIT 1
                )
                SET p.temp_user_id = u.id 
                WHERE u.user_type = 'customer' AND p.temp_user_id IS NULL
            ");
        }

        // Update v2_wallets table
        if ($schema->hasTable('v2_wallets')) {
            $this->addSql('ALTER TABLE v2_wallets ADD COLUMN temp_user_id INT DEFAULT NULL');
            
            // Update wallets for system users
            $this->addSql("
                UPDATE v2_wallets w 
                JOIN v2_users u ON w.user_id = u.id 
                SET w.temp_user_id = u.id 
                WHERE u.user_type = 'system'
            ");
            
            // Update wallets for customer users
            $this->addSql("
                UPDATE v2_wallets w 
                JOIN v2_users u ON w.user_id = (
                    SELECT cu.id 
                    FROM v2_customer_users cu 
                    WHERE cu.email = u.email 
                    AND u.user_type = 'customer'
                    LIMIT 1
                )
                SET w.temp_user_id = u.id 
                WHERE u.user_type = 'customer' AND w.temp_user_id IS NULL
            ");
        }

        // Update v2_credit_accounts table
        if ($schema->hasTable('v2_credit_accounts')) {
            $this->addSql('ALTER TABLE v2_credit_accounts ADD COLUMN temp_user_id INT DEFAULT NULL');
            
            // Update credit accounts for system users
            $this->addSql("
                UPDATE v2_credit_accounts c 
                JOIN v2_users u ON c.user_id = u.id 
                SET c.temp_user_id = u.id 
                WHERE u.user_type = 'system'
            ");
            
            // Update credit accounts for customer users
            $this->addSql("
                UPDATE v2_credit_accounts c 
                JOIN v2_users u ON c.user_id = (
                    SELECT cu.id 
                    FROM v2_customer_users cu 
                    WHERE cu.email = u.email 
                    AND u.user_type = 'customer'
                    LIMIT 1
                )
                SET c.temp_user_id = u.id 
                WHERE u.user_type = 'customer' AND c.temp_user_id IS NULL
            ");
        }
    }

    public function down(Schema $schema): void
    {
        // This would be complex to reverse, so we'll keep it simple
        // In production, you'd want to implement proper rollback
        $this->addSql('DROP TABLE IF EXISTS v2_users');
        
        // Remove temporary columns
        if ($schema->hasTable('v2_payments')) {
            $this->addSql('ALTER TABLE v2_payments DROP COLUMN IF EXISTS temp_user_id');
        }
        
        if ($schema->hasTable('v2_wallets')) {
            $this->addSql('ALTER TABLE v2_wallets DROP COLUMN IF EXISTS temp_user_id');
        }
        
        if ($schema->hasTable('v2_credit_accounts')) {
            $this->addSql('ALTER TABLE v2_credit_accounts DROP COLUMN IF EXISTS temp_user_id');
        }
    }
}