<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure;

use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\ReadOnlyMiddleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class HeliosConnectionFactory
{
    private ?Connection $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $user,
        #[\SensitiveParameter]
        private readonly string $password,
        private readonly bool $writeEnabled = false,
        private readonly int $loginTimeout = 10,
        private readonly int $queryTimeout = 60,
    ) {
    }

    public function getConnection(): Connection
    {
        if ($this->connection !== null && $this->isAlive($this->connection)) {
            return $this->connection;
        }

        $this->invalidate();

        $configuration = new Configuration();
        if (!$this->writeEnabled) {
            $configuration->setMiddlewares([new ReadOnlyMiddleware()]);
        }

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlsrv',
            'host' => $this->host,
            'port' => $this->port,
            'dbname' => $this->database,
            'user' => $this->user,
            'password' => $this->password,
            'driverOptions' => [
                'Encrypt' => 'no',
                'TrustServerCertificate' => 'yes',
                'LoginTimeout' => $this->loginTimeout,
            ],
        ], $configuration);

        try {
            $nativeConnection = $this->connection->getNativeConnection();
            if ($nativeConnection instanceof \PDO) {
                $nativeConnection->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, $this->queryTimeout);
            }
        } catch (\Throwable) {
        }

        return $this->connection;
    }

    public function isWriteEnabled(): bool
    {
        return $this->writeEnabled;
    }

    public function invalidate(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $this->connection->close();
        } catch (\Throwable) {
        }
        $this->connection = null;
    }

    private function isAlive(Connection $connection): bool
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
