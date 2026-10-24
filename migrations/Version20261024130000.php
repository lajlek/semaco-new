<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partner article details and fetch run kind';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_partner_item ADD data_url LONGTEXT DEFAULT NULL, ADD detail_parameters JSON NOT NULL, ADD details_fetched_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE app_partner_item SET detail_parameters = JSON_OBJECT()');
        $this->addSql('ALTER TABLE app_partner_fetch_run ADD kind VARCHAR(16) DEFAULT \'listing\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_partner_item DROP data_url, DROP detail_parameters, DROP details_fetched_at');
        $this->addSql('ALTER TABLE app_partner_fetch_run DROP kind');
    }
}
