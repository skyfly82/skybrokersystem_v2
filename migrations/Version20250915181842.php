<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915181842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE secret_audit_logs ADD CONSTRAINT FK_FE8D6A367176C07 FOREIGN KEY (secret_id) REFERENCES secrets (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE v2_notifications CHANGE `read` is_read TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE secret_audit_logs DROP FOREIGN KEY FK_FE8D6A367176C07');
        $this->addSql('ALTER TABLE v2_notifications CHANGE is_read `read` TINYINT(1) DEFAULT NULL');
    }
}
