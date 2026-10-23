<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023204812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Redirects for changed shop URLs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_redirect (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(768) NOT NULL, destination VARCHAR(2048) NOT NULL, permanent TINYINT(1) DEFAULT 1 NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, count INT DEFAULT 0 NOT NULL, last_accessed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_redirect_source (source), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_redirect');
    }
}
