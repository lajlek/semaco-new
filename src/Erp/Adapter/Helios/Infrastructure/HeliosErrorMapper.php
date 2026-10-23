<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure;

use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\HeliosWriteForbiddenException;
use App\Erp\Port\Common\Exception\ErpUnavailableException;
use App\Erp\Port\Common\Exception\ErpValidationException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Throwable;

final class HeliosErrorMapper
{
    public static function map(Throwable $exception): \Exception
    {
        if ($exception instanceof ErpValidationException || $exception instanceof ErpUnavailableException || $exception instanceof HeliosWriteForbiddenException) {
            return $exception;
        }

        [$sql, $params] = self::extractQuery($exception);

        if (
            $exception instanceof ConnectionException ||
            $exception instanceof DeadlockException ||
            $exception instanceof LockWaitTimeoutException
        ) {
            return ErpUnavailableException::fromThrowable($exception, $sql, $params);
        }

        if (
            $exception instanceof UniqueConstraintViolationException ||
            $exception instanceof NotNullConstraintViolationException ||
            $exception instanceof DatabaseObjectNotFoundException
        ) {
            return new ErpValidationException(
                message: $exception->getMessage(),
                erpMessage: $exception->getMessage(),
                previous: $exception,
                erpRequest: $sql,
                erpRequestParams: $params,
            );
        }

        if ($exception instanceof DriverException || $exception instanceof DBALException) {
            return ErpUnavailableException::fromThrowable($exception, $sql, $params);
        }

        return ErpUnavailableException::fromThrowable($exception, $sql, $params);
    }

    /** @return array{0: ?string, 1: array<int|string, mixed>} */
    private static function extractQuery(Throwable $exception): array
    {
        if ($exception instanceof DriverException) {
            $query = $exception->getQuery();
            if ($query !== null) {
                return [$query->getSQL(), $query->getParams()];
            }
        }

        return [null, []];
    }
}
