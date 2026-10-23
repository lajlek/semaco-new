<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Attribute;

final class HeliosAttributeValueKey
{
    public static function of(mixed $dictionaryId, string $value): string
    {
        return is_numeric($dictionaryId) && (int) $dictionaryId > 0
            ? (string) (int) $dictionaryId
            : md5($value);
    }
}
