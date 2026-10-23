<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023210600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sales representatives and B2B customer fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_sales_representative (id INT AUTO_INCREMENT NOT NULL, external_identifier VARCHAR(128) NOT NULL, code VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone_number VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_sales_representative_external_id (external_identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_customer ADD sales_representative_id INT DEFAULT NULL, ADD company_name VARCHAR(255) DEFAULT NULL, ADD identification_number VARCHAR(32) DEFAULT NULL, ADD vat_number VARCHAR(32) DEFAULT NULL, ADD invoice_email VARCHAR(255) DEFAULT NULL, ADD vat_payer TINYINT(1) DEFAULT 0 NOT NULL, ADD gdpr_agreed TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE sylius_customer ADD CONSTRAINT FK_7E82D5E68B54B08B FOREIGN KEY (sales_representative_id) REFERENCES app_sales_representative (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7E82D5E68B54B08B ON sylius_customer (sales_representative_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer DROP FOREIGN KEY FK_7E82D5E68B54B08B');
        $this->addSql('DROP TABLE app_sales_representative');
        $this->addSql('DROP INDEX IDX_7E82D5E68B54B08B ON sylius_customer');
        $this->addSql('ALTER TABLE sylius_customer DROP sales_representative_id, DROP company_name, DROP identification_number, DROP vat_number, DROP invoice_email, DROP vat_payer, DROP gdpr_agreed');
    }
}
