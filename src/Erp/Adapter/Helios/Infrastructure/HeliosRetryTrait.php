<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure;

use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\HeliosWriteForbiddenException;
use App\Erp\Port\Common\Exception\ErpUnavailableException;
use App\Erp\Port\Common\Exception\ErpValidationException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Throwable;

/** @property HeliosConnectionFactory $connections */
trait HeliosRetryTrait
{
    /**
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    private function runWithRetry(callable $query): mixed
    {
        try {
            return $query();
        } catch (ErpValidationException | ErpUnavailableException | HeliosWriteForbiddenException $alreadyMapped) {
            throw $alreadyMapped;
        } catch (Throwable $first) {
            if (!$this->isTransientConnectionError($first)) {
                throw HeliosErrorMapper::map($first);
            }

            $this->connections->invalidate();
            usleep(500_000);

            try {
                return $query();
            } catch (ErpValidationException | ErpUnavailableException | HeliosWriteForbiddenException $alreadyMapped) {
                throw $alreadyMapped;
            } catch (Throwable $second) {
                throw HeliosErrorMapper::map($second);
            }
        }
    }

    private function isTransientConnectionError(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }
        if ($e instanceof DriverException) {
            $msg = strtolower($e->getMessage());

            foreach (['physical connection', 'tcp provider', 'communication link failure', 'connection is not usable'] as $needle) {
                if (str_contains($msg, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
