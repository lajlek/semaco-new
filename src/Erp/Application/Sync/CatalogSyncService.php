<?php

declare(strict_types=1);

namespace App\Erp\Application\Sync;

use App\Catalog\BrandMenu\BrandMenuSynchronizer;
use App\Erp\Application\Catalog\AttributeGroupImporter;
use App\Erp\Application\Catalog\ProductAssociationImporter;
use App\Erp\Application\Catalog\ProductImporter;
use App\Erp\Application\Catalog\TaxonImporter;
use App\Erp\Application\Customer\OrganizationImporter;
use App\Erp\Application\Customer\SalesRepresentativeImporter;
use App\Erp\Application\ImportResult;
use App\Erp\Application\Inventory\StockImporter;
use App\Erp\Application\Pricing\PriceImporter;
use App\Erp\Application\Pricing\PriceLevelImporter;
use App\Erp\Application\Sync\Watermark\SyncWatermarkRecorder;
use App\Erp\Port\Catalog\Dto\ProductSnapshot;
use App\Erp\Watermark\Entity\ErpSyncWatermark;

final readonly class CatalogSyncService
{
    public const STEP_TAXONS = 'taxons';

    public const STEP_ATTRIBUTES = 'attributes';

    public const STEP_PRODUCTS = 'products';

    public const STEP_PRICES = 'prices';

    public const STEP_STOCK = 'stock';

    public const STEP_ORGANIZATIONS = 'organizations';

    public const STEP_ASSOCIATIONS = 'associations';

    public const STEP_SALES_REPRESENTATIVES = 'sales_representatives';

    public const STEPS = [self::STEP_TAXONS, self::STEP_ATTRIBUTES, self::STEP_PRODUCTS, self::STEP_ASSOCIATIONS, self::STEP_PRICES, self::STEP_STOCK, self::STEP_ORGANIZATIONS, self::STEP_SALES_REPRESENTATIVES];

    public function __construct(
        private TaxonImporter $taxonImporter,
        private AttributeGroupImporter $attributeGroupImporter,
        private ProductImporter $productImporter,
        private PriceLevelImporter $priceLevelImporter,
        private PriceImporter $priceImporter,
        private StockImporter $stockImporter,
        private OrganizationImporter $organizationImporter,
        private ProductAssociationImporter $associationImporter,
        private SalesRepresentativeImporter $salesRepresentativeImporter,
        private SyncWatermarkRecorder $watermarks,
        private BrandMenuSynchronizer $brandMenu,
    ) {
    }

    /**
     * @param list<string> $steps
     * @param callable(ImportResult): void $onStepFinished
     */
    public function sync(array $steps, bool $full, callable $onStepFinished): void
    {
        $touchedVariants = [];

        if (in_array(self::STEP_TAXONS, $steps, true)) {
            $onStepFinished($this->taxonImporter->import());
        }

        if (in_array(self::STEP_ATTRIBUTES, $steps, true)) {
            $onStepFinished($this->attributeGroupImporter->import());
        }

        if (in_array(self::STEP_PRODUCTS, $steps, true)) {
            $runStartedAt = new \DateTimeImmutable();
            $since = $this->since(ErpSyncWatermark::DOMAIN_PRODUCT, $full);
            $maxModifiedAt = null;
            $this->watermarks->recordAttempt(ErpSyncWatermark::DOMAIN_PRODUCT);
            $result = $this->productImporter->importChangedSince(
                $since,
                static function (ProductSnapshot $snapshot) use (&$touchedVariants, &$maxModifiedAt): void {
                    foreach ($snapshot->variants as $variant) {
                        $touchedVariants[] = $variant->externalIdentifier;
                    }
                    if ($maxModifiedAt === null || $snapshot->modifiedAt > $maxModifiedAt) {
                        $maxModifiedAt = $snapshot->modifiedAt;
                    }
                },
            );
            $this->watermarks->recordPullSuccess(ErpSyncWatermark::DOMAIN_PRODUCT, $maxModifiedAt, $runStartedAt, $since);
            $onStepFinished($result);
            $onStepFinished($this->brandMenu->sync());
        }

        if (in_array(self::STEP_ASSOCIATIONS, $steps, true)) {
            $onStepFinished($this->associationImporter->import());
        }

        if (in_array(self::STEP_PRICES, $steps, true)) {
            $onStepFinished($this->priceLevelImporter->import());

            if (!$full && $touchedVariants !== [] && $this->watermarks->getCursor(ErpSyncWatermark::DOMAIN_PRICE) !== null) {
                $onStepFinished($this->priceImporter->importForVariants($touchedVariants));
            }

            $runStartedAt = new \DateTimeImmutable();
            $since = $this->since(ErpSyncWatermark::DOMAIN_PRICE, $full);
            $maxModifiedAt = null;
            $this->watermarks->recordAttempt(ErpSyncWatermark::DOMAIN_PRICE);
            $result = $this->priceImporter->importChangedSince($since, $maxModifiedAt);
            $this->watermarks->recordPullSuccess(ErpSyncWatermark::DOMAIN_PRICE, $maxModifiedAt, $runStartedAt, $since);
            $onStepFinished($result);
        }

        if (in_array(self::STEP_STOCK, $steps, true)) {
            if (!$full && $touchedVariants !== [] && $this->watermarks->getCursor(ErpSyncWatermark::DOMAIN_STOCK) !== null) {
                $onStepFinished($this->stockImporter->importForVariants($touchedVariants));
            }

            $runStartedAt = new \DateTimeImmutable();
            $since = $this->since(ErpSyncWatermark::DOMAIN_STOCK, $full);
            $maxModifiedAt = null;
            $this->watermarks->recordAttempt(ErpSyncWatermark::DOMAIN_STOCK);
            $result = $this->stockImporter->importChangedSince($since, $maxModifiedAt);
            $this->watermarks->recordPullSuccess(ErpSyncWatermark::DOMAIN_STOCK, $maxModifiedAt, $runStartedAt, $since);
            $onStepFinished($result);
        }

        if (in_array(self::STEP_ORGANIZATIONS, $steps, true)) {
            if (!in_array(self::STEP_PRICES, $steps, true)) {
                $onStepFinished($this->priceLevelImporter->import());
            }

            $runStartedAt = new \DateTimeImmutable();
            $since = $this->since(ErpSyncWatermark::DOMAIN_ORGANIZATION, $full);
            $maxModifiedAt = null;
            $this->watermarks->recordAttempt(ErpSyncWatermark::DOMAIN_ORGANIZATION);
            $result = $this->organizationImporter->importChangedSince($since, $maxModifiedAt);
            $this->watermarks->recordPullSuccess(ErpSyncWatermark::DOMAIN_ORGANIZATION, $maxModifiedAt, $runStartedAt, $since);
            $onStepFinished($result);
        }

        if (in_array(self::STEP_SALES_REPRESENTATIVES, $steps, true)) {
            $onStepFinished($this->salesRepresentativeImporter->import());
        }
    }

    /**
     * @param list<string> $productExternalIdentifiers
     * @param callable(ImportResult): void $onStepFinished
     */
    public function syncProducts(array $productExternalIdentifiers, callable $onStepFinished): void
    {
        $touchedVariants = [];
        $onStepFinished($this->productImporter->importByIds(
            $productExternalIdentifiers,
            static function (ProductSnapshot $snapshot) use (&$touchedVariants): void {
                foreach ($snapshot->variants as $variant) {
                    $touchedVariants[] = $variant->externalIdentifier;
                }
            },
        ));

        $onStepFinished($this->brandMenu->sync());

        if ($touchedVariants !== []) {
            $onStepFinished($this->priceImporter->importForVariants($touchedVariants));
            $onStepFinished($this->stockImporter->importForVariants($touchedVariants));
        }
    }

    private function since(string $domain, bool $full): \DateTimeImmutable
    {
        $epoch = new \DateTimeImmutable('1970-01-01 00:00:00');
        if ($full) {
            return $epoch;
        }

        return $this->watermarks->getCursor($domain) ?? $epoch;
    }
}
