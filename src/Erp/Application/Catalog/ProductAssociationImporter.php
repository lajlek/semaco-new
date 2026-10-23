<?php

declare(strict_types=1);

namespace App\Erp\Application\Catalog;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Catalog\ProductAssociationSyncInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Model\ProductAssociationInterface;
use Sylius\Component\Product\Model\ProductAssociationTypeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class ProductAssociationImporter
{
    private const TYPE_NAMES = [
        ProductAssociationSyncInterface::TYPE_ACCESSORY => 'Příslušenství',
        ProductAssociationSyncInterface::TYPE_RELATED => 'Související',
        ProductAssociationSyncInterface::TYPE_ALTERNATE => 'Alternativní',
    ];

    /** @var array<string, Product|null> */
    private array $products = [];

    /**
     * @param RepositoryInterface<ProductAssociationTypeInterface> $associationTypeRepository
     * @param FactoryInterface<ProductAssociationTypeInterface> $associationTypeFactory
     * @param FactoryInterface<ProductAssociationInterface> $associationFactory
     */
    public function __construct(
        private readonly ProductAssociationSyncInterface $associationSync,
        private readonly RepositoryInterface $associationTypeRepository,
        private readonly FactoryInterface $associationTypeFactory,
        private readonly FactoryInterface $associationFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $localeCode,
    ) {
    }

    public function import(): ImportResult
    {
        $result = new ImportResult('associations');
        $types = [];
        $desired = [];

        foreach ($this->associationSync->pullAll() as $snapshot) {
            $owner = $this->product($snapshot->ownerExternalIdentifier);
            if ($owner === null || !$owner->isSyncWithErp()) {
                $result->skipped();

                continue;
            }

            $types[$snapshot->typeCode] ??= $this->associationType($snapshot->typeCode);
            foreach ($snapshot->targetExternalIdentifiers as $targetExternalIdentifier) {
                $target = $this->product($targetExternalIdentifier);
                if ($target !== null && $target !== $owner) {
                    $desired[(int) $owner->getId()][$snapshot->typeCode][(int) $target->getId()] = $target;
                }
            }
        }

        foreach ($desired as $ownerId => $byType) {
            $owner = $this->entityManager->find(Product::class, $ownerId);
            if (!$owner instanceof Product) {
                continue;
            }

            foreach ($byType as $typeCode => $targets) {
                $association = null;
                foreach ($owner->getAssociations() as $existing) {
                    if ($existing->getType()?->getCode() === $typeCode) {
                        $association = $existing;
                    }
                }

                if ($association === null) {
                    $association = $this->associationFactory->createNew();
                    $association->setType($types[$typeCode]);
                    $owner->addAssociation($association);
                    $this->entityManager->persist($association);
                    $result->created();
                } else {
                    $result->updated();
                }

                foreach ($association->getAssociatedProducts() as $product) {
                    if (!isset($targets[(int) $product->getId()])) {
                        $association->removeAssociatedProduct($product);
                    }
                }
                foreach ($targets as $target) {
                    if (!$association->hasAssociatedProduct($target)) {
                        $association->addAssociatedProduct($target);
                    }
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->products = [];

        return $result;
    }

    private function product(string $externalIdentifier): ?Product
    {
        if (array_key_exists($externalIdentifier, $this->products)) {
            return $this->products[$externalIdentifier];
        }

        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['externalIdentifier' => $externalIdentifier]);
        if (!$product instanceof Product) {
            $variant = $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['externalIdentifier' => $externalIdentifier]);
            $product = $variant instanceof ProductVariant ? $variant->getProduct() : null;
        }

        return $this->products[$externalIdentifier] = $product instanceof Product ? $product : null;
    }

    private function associationType(string $code): ProductAssociationTypeInterface
    {
        $type = $this->associationTypeRepository->findOneBy(['code' => $code]);
        if (!$type instanceof ProductAssociationTypeInterface) {
            $type = $this->associationTypeFactory->createNew();
            $type->setCode($code);
            $type->setCurrentLocale($this->localeCode);
            $type->setFallbackLocale($this->localeCode);
            $type->setName(self::TYPE_NAMES[$code] ?? $code);
            $this->entityManager->persist($type);
            $this->entityManager->flush();
        }

        return $type;
    }
}
