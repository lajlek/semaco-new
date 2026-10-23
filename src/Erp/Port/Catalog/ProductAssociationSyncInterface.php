<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog;

use App\Erp\Port\Catalog\Dto\ProductAssociationSnapshot;

interface ProductAssociationSyncInterface
{
    public const TYPE_ACCESSORY = 'accessory_products';

    public const TYPE_RELATED = 'related_products';

    public const TYPE_ALTERNATE = 'alternate_products';

    /** @return iterable<ProductAssociationSnapshot> */
    public function pullAll(): iterable;
}
