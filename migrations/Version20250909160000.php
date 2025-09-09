<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250909160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add analytics events and CMS pages tables';
    }

    public function up(Schema $schema): void
    {
        // Analytics events
        $this->addSql('CREATE TABLE v2_analytics_events (
            id INT AUTO_INCREMENT NOT NULL,
            type VARCHAR(50) NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            endpoint VARCHAR(255) DEFAULT NULL,
            method VARCHAR(10) DEFAULT NULL,
            status_code INT DEFAULT NULL,
            duration_ms INT DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            session_id VARCHAR(64) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            user_type VARCHAR(30) DEFAULT NULL,
            payload JSON DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_analytics_type (type),
            INDEX idx_analytics_created (created_at),
            INDEX idx_analytics_endpoint (endpoint),
            INDEX idx_analytics_status (status_code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // CMS pages
        $this->addSql('CREATE TABLE v2_cms_pages (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL,
            content LONGTEXT NOT NULL,
            excerpt LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            published_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            author_id INT DEFAULT NULL,
            meta_title VARCHAR(255) DEFAULT NULL,
            meta_description LONGTEXT DEFAULT NULL,
            UNIQUE INDEX UNIQ_CMS_SLUG (slug),
            INDEX idx_cms_status (status),
            INDEX idx_cms_published (published_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS v2_analytics_events');
        $this->addSql('DROP TABLE IF EXISTS v2_cms_pages');
    }
}

