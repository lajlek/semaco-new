<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure;

final class HeliosRow
{
    /** @param array<string, mixed> $row */
    public static function string(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /** @param array<string, mixed> $row */
    public static function int(array $row, string $column): ?int
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $row */
    public static function positiveFloat(array $row, string $column): ?float
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /** @param array<string, mixed> $row */
    public static function bool(array $row, string $column): bool
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) && (bool) (int) $value;
    }

    /** @param array<string, mixed> $row */
    public static function dateTime(array $row, string ...$columns): \DateTimeImmutable
    {
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if (is_string($value) && $value !== '') {
                try {
                    return new \DateTimeImmutable($value);
                } catch (\Exception) {
                }
            }
        }

        return new \DateTimeImmutable('1970-01-01 00:00:00');
    }

    /** @param array<string, mixed> $row */
    public static function float(array $row, string $column): ?float
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    public static function toMinorUnit(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function formatSince(\DateTimeInterface $since): string
    {
        return $since->format('Y-m-d H:i:s.v');
    }
}
