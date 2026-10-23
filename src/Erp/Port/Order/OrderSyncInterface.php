<?php

declare(strict_types=1);

namespace App\Erp\Port\Order;

use App\Erp\Port\Order\Dto\OrderPushRequest;

interface OrderSyncInterface
{
    /** @return string identifier of the order document in the ERP */
    public function push(OrderPushRequest $request): string;
}
