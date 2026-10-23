<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023211514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product documents and image metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_product_document (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, path VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, external_identifier VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, INDEX IDX_87E701404584665A (product_id), INDEX IDX_product_document_external_id (external_identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_product_document ADD CONSTRAINT FK_87E701404584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_product_image ADD external_identifier VARCHAR(255) DEFAULT NULL, ADD description VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_product_image_external_id ON sylius_product_image (external_identifier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_product_document DROP FOREIGN KEY FK_87E701404584665A');
        $this->addSql('DROP TABLE app_product_document');
        $this->addSql('DROP INDEX IDX_product_image_external_id ON sylius_product_image');
        $this->addSql('ALTER TABLE sylius_product_image DROP external_identifier, DROP description');
    }
}
