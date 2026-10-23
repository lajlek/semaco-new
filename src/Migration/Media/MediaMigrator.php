<?php

declare(strict_types=1);

namespace App\Migration\Media;

use App\Cms\Entity\Banner;
use App\Entity\Product\Brand;
use App\Entity\Product\Product;
use App\Entity\Product\ProductDocument;
use App\Entity\Product\ProductImage;
use App\Entity\Product\ProductVariant;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class MediaMigrator
{
    private const FLUSH_EVERY = 200;

    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        #[Autowire('%sylius_core.images_dir%')]
        private string $imagesDirectory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function migrateImages(string $sourceDirectory, bool $dryRun): ImportResult
    {
        $result = new ImportResult('images');
        $old = $this->oldShop->getConnection();

        /** @var list<array{id: int|string, path: string, type: string|null, position: int|string, description: string|null, external_identifier: string|null, product_ext: string|null}> $rows */
        $rows = $old->fetchAllAssociative(
            'SELECT i.id, i.path, i.type, i.position, i.description, i.externalIdentifier AS external_identifier, p.externalIdentifier AS product_ext
             FROM sylius_product_image i JOIN sylius_product p ON p.id = i.owner_id
             ORDER BY i.owner_id ASC, i.position ASC, i.id ASC',
        );

        /** @var list<array{image_id: int|string, variant_ext: string|null}> $links */
        $links = $old->fetchAllAssociative(
            'SELECT l.image_id, v.externalIdentifier AS variant_ext
             FROM sylius_product_image_product_variants l JOIN sylius_product_variant v ON v.id = l.variant_id',
        );
        $variantLinks = [];
        foreach ($links as $link) {
            if ($link['variant_ext'] !== null) {
                $variantLinks[(string) $link['image_id']][] = $link['variant_ext'];
            }
        }

        $processed = 0;
        foreach ($rows as $row) {
            $product = $row['product_ext'] === null ? null : $this->entityManager->getRepository(Product::class)->findOneBy(['externalIdentifier' => $row['product_ext']]);
            if (!$product instanceof Product) {
                $result->skipped();

                continue;
            }

            if (!$this->copyFile($sourceDirectory, $row['path'], $dryRun)) {
                $result->warn(sprintf('Chybí soubor %s (produkt %s)', $row['path'], $row['product_ext']));
                $result->skipped();

                continue;
            }

            $image = $this->findImage($product, $row['path']);
            if ($image === null) {
                $image = new ProductImage();
                $image->setPath($row['path']);
                $product->addImage($image);
                $result->created();
            } else {
                $result->updated();
            }

            $image->setType($row['type']);
            $image->setPosition((int) $row['position']);
            $image->setDescription($row['description']);
            $image->setExternalIdentifier($row['external_identifier']);

            foreach ($variantLinks[(string) $row['id']] ?? [] as $variantExternalIdentifier) {
                $variant = $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['externalIdentifier' => $variantExternalIdentifier]);
                if ($variant instanceof ProductVariant && $variant->getProduct() === $product && !$image->hasProductVariant($variant)) {
                    $image->addProductVariant($variant);
                }
            }

            if (!$dryRun && ++$processed % self::FLUSH_EVERY === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }
        $this->entityManager->clear();

        return $result;
    }

    public function migrateDocuments(string $sourceDirectory, bool $dryRun): ImportResult
    {
        $result = new ImportResult('documents');

        /** @var list<array{path: string|null, name: string|null, external_identifier: string|null, product_ext: string|null}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT d.path, d.name, d.externalIdentifier AS external_identifier, p.externalIdentifier AS product_ext
             FROM mergocz_product_document d JOIN sylius_product p ON p.id = d.owner_id
             ORDER BY d.owner_id ASC, d.id ASC',
        );

        foreach ($rows as $position => $row) {
            $product = $row['product_ext'] === null ? null : $this->entityManager->getRepository(Product::class)->findOneBy(['externalIdentifier' => $row['product_ext']]);
            if (!$product instanceof Product || $row['path'] === null) {
                $result->skipped();

                continue;
            }

            if (!$this->copyFile($sourceDirectory, $row['path'], $dryRun)) {
                $result->warn(sprintf('Chybí soubor %s (produkt %s)', $row['path'], $row['product_ext']));
                $result->skipped();

                continue;
            }

            $document = null;
            foreach ($product->getDocuments() as $existing) {
                if ($existing->getPath() === $row['path']) {
                    $document = $existing;
                }
            }

            if ($document === null) {
                $document = new ProductDocument($product, $row['path']);
                $product->addDocument($document);
                $result->created();
            } else {
                $result->updated();
            }

            $document->setName($row['name']);
            $document->setExternalIdentifier($row['external_identifier']);
            $document->setPosition($position);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }
        $this->entityManager->clear();

        return $result;
    }

    public function migrateBrandLogos(string $sourceDirectory, bool $dryRun): ImportResult
    {
        $result = new ImportResult('brand-logos');

        /** @var list<array{code: string, path: string|null}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT b.code, i.path FROM mergocz_brand b JOIN mergocz_brand_image i ON i.owner_id = b.id',
        );

        foreach ($rows as $row) {
            $brand = $this->entityManager->getRepository(Brand::class)->findOneBy(['code' => $row['code']]);
            if (!$brand instanceof Brand || $row['path'] === null || !$this->copyFile($sourceDirectory, $row['path'], $dryRun)) {
                $result->skipped();

                continue;
            }

            $brand->setLogoPath($row['path']);
            $result->updated();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    public function migrateBanners(string $sourceDirectory, bool $dryRun): ImportResult
    {
        $result = new ImportResult('banners');

        /** @var list<array{code: string, path: string|null}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            "SELECT code, path FROM bitbag_cms_media WHERE type = 'image' AND enabled = 1",
        );

        foreach ($rows as $row) {
            $code = $row['code'] === 'banner' ? 'homepage' : $row['code'];
            $path = $row['path'] === null ? null : preg_replace('#^/?media/image/#', '', $row['path']);
            if ($path === null || $path === '' || $this->entityManager->getRepository(Banner::class)->findOneBy(['code' => $code]) instanceof Banner) {
                $result->skipped();

                continue;
            }

            if (!$this->copyFile($sourceDirectory, $path, $dryRun)) {
                $result->warn(sprintf('Chybí soubor banneru %s', $path));
                $result->skipped();

                continue;
            }

            $banner = new Banner();
            $banner->setCode($code);
            $banner->setPath($path);
            $this->entityManager->persist($banner);
            $result->created();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    private function findImage(Product $product, string $path): ?ProductImage
    {
        foreach ($product->getImages() as $image) {
            if ($image instanceof ProductImage && $image->getPath() === $path) {
                return $image;
            }
        }

        return null;
    }

    private function copyFile(string $sourceDirectory, string $path, bool $dryRun): bool
    {
        $target = $this->imagesDirectory . '/' . $path;
        if ($this->filesystem->exists($target)) {
            return true;
        }

        foreach ([$sourceDirectory . '/' . $path, $sourceDirectory . '/image/' . $path, $sourceDirectory . '/media/image/' . $path] as $candidate) {
            if (is_file($candidate)) {
                if (!$dryRun) {
                    $this->filesystem->copy($candidate, $target);
                }

                return true;
            }
        }

        return false;
    }
}
