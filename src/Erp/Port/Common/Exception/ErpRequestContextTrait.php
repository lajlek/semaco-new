<?php

declare(strict_types=1);

namespace App\Erp\Port\Common\Exception;

trait ErpRequestContextTrait
{
    private ?string $erpRequest = null;

    /** @var array<int|string, mixed> */
    private array $erpRequestParams = [];

    public function getErpRequest(): ?string
    {
        return $this->erpRequest;
    }

    /** @return array<int|string, mixed> */
    public function getErpRequestParams(): array
    {
        return $this->erpRequestParams;
    }

    /** @param array<int|string, mixed> $params */
    public function replaceErpRequestContext(?string $request, array $params): void
    {
        $this->erpRequest = $request;
        $this->erpRequestParams = $params;
    }
}
