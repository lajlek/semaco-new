<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023220839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registration requests and contact form messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_contact_message (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, legacy_id INT DEFAULT NULL, INDEX IDX_764834779395C3F3 (customer_id), INDEX IDX_contact_message_created_at (created_at), UNIQUE INDEX UNIQ_contact_message_legacy_id (legacy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_contact_message_answer (id INT AUTO_INCREMENT NOT NULL, admin_user_id INT DEFAULT NULL, contact_message_id INT NOT NULL, from_admin TINYINT(1) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', legacy_id INT DEFAULT NULL, INDEX IDX_B31DEA706352511C (admin_user_id), INDEX IDX_B31DEA7094C34ABE (contact_message_id), UNIQUE INDEX UNIQ_contact_message_answer_legacy_id (legacy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_registration_request (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, identification_number VARCHAR(255) DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, street_number VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, postcode VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, legacy_id INT DEFAULT NULL, INDEX IDX_90B652EE9395C3F3 (customer_id), INDEX IDX_registration_request_created_at (created_at), UNIQUE INDEX UNIQ_registration_request_legacy_id (legacy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_contact_message ADD CONSTRAINT FK_764834779395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_contact_message_answer ADD CONSTRAINT FK_B31DEA706352511C FOREIGN KEY (admin_user_id) REFERENCES sylius_admin_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_contact_message_answer ADD CONSTRAINT FK_B31DEA7094C34ABE FOREIGN KEY (contact_message_id) REFERENCES app_contact_message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_registration_request ADD CONSTRAINT FK_90B652EE9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_contact_message DROP FOREIGN KEY FK_764834779395C3F3');
        $this->addSql('ALTER TABLE app_contact_message_answer DROP FOREIGN KEY FK_B31DEA706352511C');
        $this->addSql('ALTER TABLE app_contact_message_answer DROP FOREIGN KEY FK_B31DEA7094C34ABE');
        $this->addSql('ALTER TABLE app_registration_request DROP FOREIGN KEY FK_90B652EE9395C3F3');
        $this->addSql('DROP TABLE app_contact_message');
        $this->addSql('DROP TABLE app_contact_message_answer');
        $this->addSql('DROP TABLE app_registration_request');
    }
}
