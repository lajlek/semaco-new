<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023233310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage banners managed in admin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_cms_banner (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, path VARCHAR(255) DEFAULT NULL, type VARCHAR(64) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, link VARCHAR(500) DEFAULT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_cms_banner_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_cms_banner');
    }
}
