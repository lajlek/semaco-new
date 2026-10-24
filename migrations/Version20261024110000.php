<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partner items preparation, approval, export batches and fetch queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_partner_export_batch (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_by VARCHAR(255) NOT NULL, item_count INT NOT NULL, content LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_partner_fetch_run (id INT AUTO_INCREMENT NOT NULL, partner_code VARCHAR(32) NOT NULL, url LONGTEXT NOT NULL, max_pages INT NOT NULL, status VARCHAR(16) NOT NULL, requested_by VARCHAR(255) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', pages INT NOT NULL, items_created INT NOT NULL, items_changed INT NOT NULL, items_unchanged INT NOT NULL, message LONGTEXT DEFAULT NULL, INDEX IDX_partner_fetch_run_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_partner_item ADD proposed_product_id INT DEFAULT NULL, ADD export_batch_id INT DEFAULT NULL, ADD family_key VARCHAR(64) DEFAULT NULL, ADD grouping_status VARCHAR(16) DEFAULT NULL, ADD prepared_name VARCHAR(255) DEFAULT NULL, ADD prepared_product_name VARCHAR(255) DEFAULT NULL, ADD prepared_taxon_code VARCHAR(255) DEFAULT NULL, ADD prepared_options JSON NOT NULL, ADD issues JSON NOT NULL, ADD overrides JSON NOT NULL, ADD approved_hash VARCHAR(64) DEFAULT NULL, ADD approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD approved_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE app_partner_item SET prepared_options = JSON_OBJECT(), issues = JSON_ARRAY(), overrides = JSON_OBJECT()');
        $this->addSql('ALTER TABLE app_partner_item ADD CONSTRAINT FK_partner_item_proposed_product FOREIGN KEY (proposed_product_id) REFERENCES sylius_product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_partner_item ADD CONSTRAINT FK_partner_item_export_batch FOREIGN KEY (export_batch_id) REFERENCES app_partner_export_batch (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8DA10C87F1034944 ON app_partner_item (proposed_product_id)');
        $this->addSql('CREATE INDEX IDX_8DA10C87FDD5FB65 ON app_partner_item (export_batch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_partner_item DROP FOREIGN KEY FK_partner_item_proposed_product');
        $this->addSql('ALTER TABLE app_partner_item DROP FOREIGN KEY FK_partner_item_export_batch');
        $this->addSql('DROP INDEX IDX_8DA10C87F1034944 ON app_partner_item');
        $this->addSql('DROP INDEX IDX_8DA10C87FDD5FB65 ON app_partner_item');
        $this->addSql('ALTER TABLE app_partner_item DROP proposed_product_id, DROP export_batch_id, DROP family_key, DROP grouping_status, DROP prepared_name, DROP prepared_product_name, DROP prepared_taxon_code, DROP prepared_options, DROP issues, DROP overrides, DROP approved_hash, DROP approved_at, DROP approved_by');
        $this->addSql('DROP TABLE app_partner_export_batch');
        $this->addSql('DROP TABLE app_partner_fetch_run');
    }
}
