<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023222223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wishlist per shop user or guest cookie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_wishlist (id INT AUTO_INCREMENT NOT NULL, shop_user_id INT DEFAULT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_94614A9C5F37A13B (token), UNIQUE INDEX UNIQ_94614A9CA45D93BF (shop_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_wishlist_item (id INT AUTO_INCREMENT NOT NULL, wishlist_id INT NOT NULL, variant_id INT NOT NULL, added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FE59C944FB8E54CD (wishlist_id), INDEX IDX_FE59C9443B69A9AF (variant_id), UNIQUE INDEX UNIQ_wishlist_item_variant (wishlist_id, variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_wishlist ADD CONSTRAINT FK_94614A9CA45D93BF FOREIGN KEY (shop_user_id) REFERENCES sylius_shop_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_wishlist_item ADD CONSTRAINT FK_FE59C944FB8E54CD FOREIGN KEY (wishlist_id) REFERENCES app_wishlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_wishlist_item ADD CONSTRAINT FK_FE59C9443B69A9AF FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_wishlist DROP FOREIGN KEY FK_94614A9CA45D93BF');
        $this->addSql('ALTER TABLE app_wishlist_item DROP FOREIGN KEY FK_FE59C944FB8E54CD');
        $this->addSql('ALTER TABLE app_wishlist_item DROP FOREIGN KEY FK_FE59C9443B69A9AF');
        $this->addSql('DROP TABLE app_wishlist');
        $this->addSql('DROP TABLE app_wishlist_item');
    }
}
