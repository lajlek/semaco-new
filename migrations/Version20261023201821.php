<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023201821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'B2B organizations, price levels, product prices, brands, ERP identifiers and sync watermarks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_brand (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, external_identifier VARCHAR(128) DEFAULT NULL, logo_path VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_brand_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_erp_sync_watermark (id INT AUTO_INCREMENT NOT NULL, domain VARCHAR(32) NOT NULL, last_pulled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_success_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_cursor VARCHAR(255) DEFAULT NULL, last_error LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_watermark_domain (domain), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_organization (id INT AUTO_INCREMENT NOT NULL, price_level_id INT DEFAULT NULL, default_address_id INT DEFAULT NULL, external_identifier VARCHAR(128) NOT NULL, name VARCHAR(255) NOT NULL, org_number VARCHAR(16) DEFAULT NULL, identification_number VARCHAR(32) DEFAULT NULL, vat_identification_number VARCHAR(32) DEFAULT NULL, bank_account VARCHAR(64) DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, street_number VARCHAR(32) DEFAULT NULL, street_number_supplement VARCHAR(32) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, zip_code VARCHAR(16) DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, customer_type VARCHAR(32) DEFAULT NULL, free_shipping TINYINT(1) DEFAULT 0 NOT NULL, sales_representative_code VARCHAR(32) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_36079FD3D0770B0 (price_level_id), INDEX IDX_36079FDBD94FB16 (default_address_id), INDEX IDX_organization_org_number (org_number), UNIQUE INDEX UNIQ_organization_external_id (external_identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_price_level (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, external_identifier VARCHAR(128) DEFAULT NULL, UNIQUE INDEX UNIQ_price_level_code (code), UNIQUE INDEX UNIQ_price_level_external_id (external_identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_product_price (id INT AUTO_INCREMENT NOT NULL, product_variant_id INT NOT NULL, price_level_id INT NOT NULL, currency_code VARCHAR(3) NOT NULL, amount INT NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_product_price_variant (product_variant_id), INDEX IDX_product_price_level (price_level_id), UNIQUE INDEX UNIQ_product_price_variant_level_currency (product_variant_id, price_level_id, currency_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_organization ADD CONSTRAINT FK_36079FD3D0770B0 FOREIGN KEY (price_level_id) REFERENCES app_price_level (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_organization ADD CONSTRAINT FK_36079FDBD94FB16 FOREIGN KEY (default_address_id) REFERENCES sylius_address (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_product_price ADD CONSTRAINT FK_F1E96429A80EF684 FOREIGN KEY (product_variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_product_price ADD CONSTRAINT FK_F1E964293D0770B0 FOREIGN KEY (price_level_id) REFERENCES app_price_level (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_address ADD organization_id INT DEFAULT NULL, ADD external_identifier VARCHAR(128) DEFAULT NULL, ADD identification_number VARCHAR(32) DEFAULT NULL, ADD vat_number VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_address ADD CONSTRAINT FK_B97FF05832C8A3DE FOREIGN KEY (organization_id) REFERENCES app_organization (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B97FF05832C8A3DE ON sylius_address (organization_id)');
        $this->addSql('CREATE INDEX IDX_address_external_id ON sylius_address (external_identifier)');
        $this->addSql('ALTER TABLE sylius_customer ADD organization_id INT DEFAULT NULL, ADD external_identifier VARCHAR(128) DEFAULT NULL, ADD sync_with_erp TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE sylius_customer ADD CONSTRAINT FK_7E82D5E632C8A3DE FOREIGN KEY (organization_id) REFERENCES app_organization (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7E82D5E632C8A3DE ON sylius_customer (organization_id)');
        $this->addSql('CREATE INDEX IDX_customer_external_id ON sylius_customer (external_identifier)');
        $this->addSql('ALTER TABLE sylius_product ADD brand_id INT DEFAULT NULL, ADD external_identifier VARCHAR(128) DEFAULT NULL, ADD skupzbo VARCHAR(64) DEFAULT NULL, ADD minimum_quantity INT DEFAULT 1 NOT NULL, ADD quantity_step INT DEFAULT 1 NOT NULL, ADD sync_with_erp TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT FK_677B9B7444F5D008 FOREIGN KEY (brand_id) REFERENCES app_brand (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_677B9B7444F5D008 ON sylius_product (brand_id)');
        $this->addSql('CREATE INDEX IDX_product_external_id ON sylius_product (external_identifier)');
        $this->addSql('ALTER TABLE sylius_product_attribute ADD filter_type INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant ADD external_identifier VARCHAR(128) DEFAULT NULL, ADD regcis VARCHAR(64) DEFAULT NULL, ADD barcode VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_variant_regcis ON sylius_product_variant (regcis)');
        $this->addSql('CREATE INDEX IDX_variant_external_id ON sylius_product_variant (external_identifier)');
        $this->addSql('ALTER TABLE sylius_taxon ADD external_identifier VARCHAR(128) DEFAULT NULL, ADD show_in_menu TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE INDEX IDX_taxon_external_id ON sylius_taxon (external_identifier)');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product DROP FOREIGN KEY FK_677B9B7444F5D008');
        $this->addSql('ALTER TABLE sylius_address DROP FOREIGN KEY FK_B97FF05832C8A3DE');
        $this->addSql('ALTER TABLE sylius_customer DROP FOREIGN KEY FK_7E82D5E632C8A3DE');
        $this->addSql('ALTER TABLE app_organization DROP FOREIGN KEY FK_36079FD3D0770B0');
        $this->addSql('ALTER TABLE app_organization DROP FOREIGN KEY FK_36079FDBD94FB16');
        $this->addSql('ALTER TABLE app_product_price DROP FOREIGN KEY FK_F1E96429A80EF684');
        $this->addSql('ALTER TABLE app_product_price DROP FOREIGN KEY FK_F1E964293D0770B0');
        $this->addSql('DROP TABLE app_brand');
        $this->addSql('DROP TABLE app_erp_sync_watermark');
        $this->addSql('DROP TABLE app_organization');
        $this->addSql('DROP TABLE app_price_level');
        $this->addSql('DROP TABLE app_product_price');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('DROP INDEX IDX_B97FF05832C8A3DE ON sylius_address');
        $this->addSql('DROP INDEX IDX_address_external_id ON sylius_address');
        $this->addSql('ALTER TABLE sylius_address DROP organization_id, DROP external_identifier, DROP identification_number, DROP vat_number');
        $this->addSql('DROP INDEX IDX_7E82D5E632C8A3DE ON sylius_customer');
        $this->addSql('DROP INDEX IDX_customer_external_id ON sylius_customer');
        $this->addSql('ALTER TABLE sylius_customer DROP organization_id, DROP external_identifier, DROP sync_with_erp');
        $this->addSql('DROP INDEX IDX_677B9B7444F5D008 ON sylius_product');
        $this->addSql('DROP INDEX IDX_product_external_id ON sylius_product');
        $this->addSql('ALTER TABLE sylius_product DROP brand_id, DROP external_identifier, DROP skupzbo, DROP minimum_quantity, DROP quantity_step, DROP sync_with_erp');
        $this->addSql('ALTER TABLE sylius_product_attribute DROP filter_type');
        $this->addSql('DROP INDEX IDX_variant_regcis ON sylius_product_variant');
        $this->addSql('DROP INDEX IDX_variant_external_id ON sylius_product_variant');
        $this->addSql('ALTER TABLE sylius_product_variant DROP external_identifier, DROP regcis, DROP barcode');
        $this->addSql('DROP INDEX IDX_taxon_external_id ON sylius_taxon');
        $this->addSql('ALTER TABLE sylius_taxon DROP external_identifier, DROP show_in_menu');
    }
}
