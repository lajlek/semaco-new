<?php

declare(strict_types=1);

namespace App\Erp\Port\Common\Exception;

interface ProvidesErpRequestContext
{
    public function getErpRequest(): ?string;

    /** @return array<int|string, mixed> */
    public function getErpRequestParams(): array;
}
