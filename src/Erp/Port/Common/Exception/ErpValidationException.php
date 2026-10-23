<?php

declare(strict_types=1);

namespace App\Erp\Port\Common\Exception;

use InvalidArgumentException;
use Throwable;

class ErpValidationException extends InvalidArgumentException implements ProvidesErpRequestContext
{
    use ErpRequestContextTrait;

    /** @param array<int|string, mixed> $erpRequestParams */
    public function __construct(
        string $message,
        public readonly ?string $field = null,
        public readonly ?string $erpMessage = null,
        ?Throwable $previous = null,
        ?string $erpRequest = null,
        array $erpRequestParams = [],
    ) {
        parent::__construct($message, 0, $previous);
        $this->erpRequest = $erpRequest;
        $this->erpRequestParams = $erpRequestParams;
    }
}
