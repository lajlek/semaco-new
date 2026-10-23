<?php

declare(strict_types=1);

namespace App\Erp\Application;

final class ImportResult
{
    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(public readonly string $domain)
    {
    }

    public function created(int $count = 1): void
    {
        $this->created += $count;
    }

    public function updated(int $count = 1): void
    {
        $this->updated += $count;
    }

    public function skipped(int $count = 1): void
    {
        $this->skipped += $count;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    /** @return list<string> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
