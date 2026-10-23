<?php

declare(strict_types=1);

namespace App\Erp\Port\Common\Exception;

use RuntimeException;
use Throwable;

class ErpUnavailableException extends RuntimeException implements ProvidesErpRequestContext
{
    use ErpRequestContextTrait;

    /** @param array<int|string, mixed> $erpRequestParams */
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $erpRequest = null,
        array $erpRequestParams = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->erpRequest = $erpRequest;
        $this->erpRequestParams = $erpRequestParams;
    }

    /** @param array<int|string, mixed> $erpRequestParams */
    public static function fromThrowable(Throwable $previous, ?string $erpRequest = null, array $erpRequestParams = []): self
    {
        return new self(
            sprintf('ERP is unavailable: %s', $previous->getMessage()),
            0,
            $previous,
            $erpRequest,
            $erpRequestParams,
        );
    }
}
