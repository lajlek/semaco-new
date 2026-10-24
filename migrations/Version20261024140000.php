<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Variant name managed in e-shop and skipped by ERP sync';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_variant ADD erp_name_locked TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_variant DROP erp_name_locked');
    }
}
