<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure\ReadOnly;

final class ReadOnlyStatementGuard
{
    private const ALLOWED_PREFIXES = ['SELECT', 'WITH'];

    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'EXEC', 'EXECUTE', 'CREATE', 'ALTER',
        'DROP', 'TRUNCATE', 'GRANT', 'REVOKE', 'DENY', 'BACKUP', 'RESTORE', 'INTO',
    ];

    public static function assertReadOnly(string $sql): void
    {
        $normalized = strtoupper(self::stripCommentsAndLiterals($sql));
        $normalized = ltrim($normalized, " \t\n\r;(");

        $allowed = false;
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $allowed = true;

                break;
            }
        }

        if (!$allowed) {
            throw HeliosWriteForbiddenException::forStatement($sql);
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/', $normalized) === 1) {
                throw HeliosWriteForbiddenException::forStatement($sql);
            }
        }
    }

    private static function stripCommentsAndLiterals(string $sql): string
    {
        $sql = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
        $sql = preg_replace('~--[^\n]*~', ' ', $sql) ?? $sql;

        return preg_replace("~'(?:[^']|'')*'~", "''", $sql) ?? $sql;
    }
}
