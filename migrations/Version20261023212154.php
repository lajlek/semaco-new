<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023212154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partner catalog items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_partner_item (id INT AUTO_INCREMENT NOT NULL, matched_variant_id INT DEFAULT NULL, partner_code VARCHAR(32) NOT NULL, order_code VARCHAR(64) NOT NULL, family_code VARCHAR(64) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, subtitle VARCHAR(255) DEFAULT NULL, source_url LONGTEXT DEFAULT NULL, detail_url LONGTEXT DEFAULT NULL, parameters JSON NOT NULL, image_urls JSON NOT NULL, price VARCHAR(32) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, availability VARCHAR(64) DEFAULT NULL, content_hash VARCHAR(64) NOT NULL, match_status VARCHAR(16) NOT NULL, workflow_state VARCHAR(16) NOT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8DA10C876B85C3A1 (matched_variant_id), INDEX IDX_partner_item_match (partner_code, match_status), UNIQUE INDEX UNIQ_partner_item_code (partner_code, order_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_partner_item ADD CONSTRAINT FK_8DA10C876B85C3A1 FOREIGN KEY (matched_variant_id) REFERENCES sylius_product_variant (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_partner_item DROP FOREIGN KEY FK_8DA10C876B85C3A1');
        $this->addSql('DROP TABLE app_partner_item');
    }
}
