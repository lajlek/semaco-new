<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Entity\Product\ProductVariant;
use App\Entity\User\ShopUser;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use App\Wishlist\Entity\Wishlist;
use App\Wishlist\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 55)]
final readonly class WishlistMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        private WishlistRepository $wishlistRepository,
    ) {
    }

    public function name(): string
    {
        return 'wishlists';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('wishlists');

        /** @var list<array{email: string, variant_ext: string|null}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT c.email, v.externalIdentifier AS variant_ext
             FROM mergocz_wishlist w
             JOIN sylius_shop_user u ON u.id = w.shop_user_id
             JOIN sylius_customer c ON c.id = u.customer_id
             JOIN mergocz_wishlist_product wp ON wp.wishlist_id = w.id
             JOIN sylius_product_variant v ON v.id = wp.variant_id
             ORDER BY wp.id ASC',
        );

        foreach ($rows as $row) {
            $shopUser = $this->entityManager->getRepository(ShopUser::class)->findOneBy(['usernameCanonical' => mb_strtolower(trim($row['email']))]);
            $variant = $row['variant_ext'] === null ? null : $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['externalIdentifier' => $row['variant_ext']]);
            if (!$shopUser instanceof ShopUser || !$variant instanceof ProductVariant) {
                $result->warn(sprintf('Přeskočeno: %s / varianta %s', $row['email'], $row['variant_ext'] ?? '—'));
                $result->skipped();

                continue;
            }

            $wishlist = $this->wishlistRepository->findOneByShopUser($shopUser);
            if ($wishlist === null) {
                $wishlist = new Wishlist();
                $wishlist->setShopUser($shopUser);
                $this->entityManager->persist($wishlist);
            }

            $wishlist->addVariant($variant) ? $result->created() : $result->updated();
            if (!$dryRun) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->clear();

        return $result;
    }
}
