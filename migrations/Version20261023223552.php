<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023223552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Online withdrawal from purchase contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_withdrawal_request (id INT AUTO_INCREMENT NOT NULL, order_id INT DEFAULT NULL, customer_id INT DEFAULT NULL, token VARCHAR(64) NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, address VARCHAR(500) DEFAULT NULL, order_number VARCHAR(64) NOT NULL, received_on VARCHAR(64) DEFAULT NULL, items LONGTEXT NOT NULL, bank_account VARCHAR(64) DEFAULT NULL, note LONGTEXT DEFAULT NULL, submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, handled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3E473DAF8D9F6D38 (order_id), INDEX IDX_3E473DAF9395C3F3 (customer_id), INDEX IDX_withdrawal_request_submitted_at (submitted_at), UNIQUE INDEX UNIQ_withdrawal_request_token (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_withdrawal_request ADD CONSTRAINT FK_3E473DAF8D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE app_withdrawal_request ADD CONSTRAINT FK_3E473DAF9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_withdrawal_request DROP FOREIGN KEY FK_3E473DAF8D9F6D38');
        $this->addSql('ALTER TABLE app_withdrawal_request DROP FOREIGN KEY FK_3E473DAF9395C3F3');
        $this->addSql('DROP TABLE app_withdrawal_request');
    }
}
