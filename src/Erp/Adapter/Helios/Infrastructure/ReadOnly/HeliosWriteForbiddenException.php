<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure\ReadOnly;

final class HeliosWriteForbiddenException extends \RuntimeException
{
    public static function forStatement(string $sql): self
    {
        return new self(sprintf(
            'Zápis do Heliosu je vypnutý (HELIOS_WRITE_ENABLED=0), odmítnutý příkaz: %s',
            mb_substr(trim($sql), 0, 120),
        ));
    }
}
