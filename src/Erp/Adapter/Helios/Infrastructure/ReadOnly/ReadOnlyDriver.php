<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure\ReadOnly;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class ReadOnlyDriver extends AbstractDriverMiddleware
{
    public function connect(#[\SensitiveParameter] array $params): Connection
    {
        return new ReadOnlyConnection(parent::connect($params));
    }
}
