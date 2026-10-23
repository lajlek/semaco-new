<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023214001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order code without spaces for search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_variant ADD regcis_compact VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_variant_regcis_compact ON sylius_product_variant (regcis_compact)');
        $this->addSql("UPDATE sylius_product_variant SET regcis_compact = REPLACE(regcis, ' ', '') WHERE regcis IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_variant_regcis_compact ON sylius_product_variant');
        $this->addSql('ALTER TABLE sylius_product_variant DROP regcis_compact');
    }
}
