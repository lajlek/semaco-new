<?php

declare(strict_types=1);

namespace App\Migration\OldShop;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;

final class OldShopConnectionFactory
{
    private ?Connection $connection = null;

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $databaseUrl,
    ) {
    }

    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($this->databaseUrl);
            $configuration = new Configuration();
            $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
            $this->connection = DriverManager::getConnection($params, $configuration);
        }

        return $this->connection;
    }
}
