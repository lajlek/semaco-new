<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Selectable partner categories with BRW limited to FUTURO and NERIOX';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_partner_category (id INT AUTO_INCREMENT NOT NULL, partner_code VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, start_url LONGTEXT DEFAULT NULL, url_keywords VARCHAR(255) DEFAULT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, pending_urls JSON DEFAULT NULL, visited_urls JSON NOT NULL, pages_fetched INT DEFAULT 0 NOT NULL, fetch_requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_partner_category_partner (partner_code, enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE app_partner_fetch_run ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_partner_fetch_run ADD CONSTRAINT FK_D8B5A3B212469DE2 FOREIGN KEY (category_id) REFERENCES app_partner_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8B5A3B212469DE2 ON app_partner_fetch_run (category_id)');
        $this->addSql('CREATE INDEX IDX_partner_item_family_code ON app_partner_item (partner_code, family_code)');
        $this->addSql("INSERT INTO app_partner_category (partner_code, name, url_keywords, enabled, visited_urls, pages_fetched) VALUES ('brw', 'FUTURO', 'futuro', 1, '[]', 0), ('brw', 'NERIOX', 'neriox', 1, '[]', 0)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_partner_item_family_code ON app_partner_item');
        $this->addSql('ALTER TABLE app_partner_fetch_run DROP FOREIGN KEY FK_D8B5A3B212469DE2');
        $this->addSql('DROP INDEX IDX_D8B5A3B212469DE2 ON app_partner_fetch_run');
        $this->addSql('ALTER TABLE app_partner_fetch_run DROP category_id');
        $this->addSql('DROP TABLE app_partner_category');
    }
}
