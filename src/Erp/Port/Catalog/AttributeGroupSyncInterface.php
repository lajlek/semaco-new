<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog;

use App\Erp\Port\Catalog\Dto\AttributeGroupSnapshot;

interface AttributeGroupSyncInterface
{
    /** @return iterable<AttributeGroupSnapshot> */
    public function pullAll(): iterable;
}
