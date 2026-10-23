<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure\ReadOnly;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class ReadOnlyConnection extends AbstractConnectionMiddleware
{
    public function prepare(string $sql): Statement
    {
        ReadOnlyStatementGuard::assertReadOnly($sql);

        return parent::prepare($sql);
    }

    public function query(string $sql): Result
    {
        ReadOnlyStatementGuard::assertReadOnly($sql);

        return parent::query($sql);
    }

    public function exec(string $sql): int
    {
        throw HeliosWriteForbiddenException::forStatement($sql);
    }

    public function beginTransaction(): bool
    {
        throw HeliosWriteForbiddenException::forStatement('BEGIN TRANSACTION');
    }
}
