<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250905RegistrationCodes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add v2_email_verification_codes table and email verification columns to preliminary registrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE v2_email_verification_codes (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, code VARCHAR(6) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, attempts INT NOT NULL, consumed_at DATETIME DEFAULT NULL, purpose VARCHAR(32) NOT NULL, pre_token VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8MB4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("ALTER TABLE v2_preliminary_registrations ADD email_verified TINYINT(1) NOT NULL DEFAULT 0, ADD email_verified_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE v2_email_verification_codes');
        $this->addSql('ALTER TABLE v2_preliminary_registrations DROP email_verified, DROP email_verified_at');
    }
}

