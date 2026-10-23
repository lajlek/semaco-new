<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023213611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CMS pages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_cms_page (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) DEFAULT NULL, slug VARCHAR(128) NOT NULL, title VARCHAR(255) NOT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description VARCHAR(500) DEFAULT NULL, content LONGTEXT NOT NULL, position INT DEFAULT 0 NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_cms_page_slug (slug), UNIQUE INDEX UNIQ_cms_page_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_cms_page');
    }
}
