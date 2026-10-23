<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023212732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Order organization, sales representative and ERP export state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order ADD organization_id INT DEFAULT NULL, ADD sales_representative_id INT DEFAULT NULL, ADD contact_customer_id INT DEFAULT NULL, ADD external_identifier VARCHAR(128) DEFAULT NULL, ADD erp_export_state VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F932C8A3DE FOREIGN KEY (organization_id) REFERENCES app_organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F98B54B08B FOREIGN KEY (sales_representative_id) REFERENCES app_sales_representative (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F9FD397C4D FOREIGN KEY (contact_customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6196A1F932C8A3DE ON sylius_order (organization_id)');
        $this->addSql('CREATE INDEX IDX_6196A1F98B54B08B ON sylius_order (sales_representative_id)');
        $this->addSql('CREATE INDEX IDX_6196A1F9FD397C4D ON sylius_order (contact_customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order DROP FOREIGN KEY FK_6196A1F932C8A3DE');
        $this->addSql('ALTER TABLE sylius_order DROP FOREIGN KEY FK_6196A1F98B54B08B');
        $this->addSql('ALTER TABLE sylius_order DROP FOREIGN KEY FK_6196A1F9FD397C4D');
        $this->addSql('DROP INDEX IDX_6196A1F932C8A3DE ON sylius_order');
        $this->addSql('DROP INDEX IDX_6196A1F98B54B08B ON sylius_order');
        $this->addSql('DROP INDEX IDX_6196A1F9FD397C4D ON sylius_order');
        $this->addSql('ALTER TABLE sylius_order DROP organization_id, DROP sales_representative_id, DROP contact_customer_id, DROP external_identifier, DROP erp_export_state');
    }
}
