<?php

declare(strict_types=1);

namespace App\Erp\Application\Sync\Watermark;

use App\Erp\Watermark\Repository\ErpSyncWatermarkRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SyncWatermarkRecorder
{
    public function __construct(
        private ErpSyncWatermarkRepositoryInterface $watermarks,
        private EntityManagerInterface $entityManager,
        private bool $cursorAtLeastRunStart = false,
    ) {
    }

    public function recordAttempt(string $domain): void
    {
        $watermark = $this->watermarks->findOrCreateByDomain($domain);
        $watermark->recordAttempt();
        $this->entityManager->flush();
    }

    public function recordSuccess(string $domain, ?\DateTimeImmutable $cursor = null): void
    {
        $watermark = $this->watermarks->findOrCreateByDomain($domain);

        $watermark->recordSuccess($cursor?->format('Y-m-d\\TH:i:s.uP'));
        $this->entityManager->flush();
    }

    public function recordPullSuccess(string $domain, ?\DateTimeImmutable $maxModifiedAt, \DateTimeImmutable $runStartedAt, \DateTimeImmutable $since): void
    {
        if (!$this->cursorAtLeastRunStart) {
            $this->recordSuccess($domain, $maxModifiedAt ?? $since);

            return;
        }

        $this->recordSuccess($domain, $maxModifiedAt !== null && $maxModifiedAt > $runStartedAt ? $maxModifiedAt : $runStartedAt);
    }

    public function recordError(string $domain, string $error): void
    {
        $watermark = $this->watermarks->findOrCreateByDomain($domain);
        $watermark->recordError($error);
        $this->entityManager->flush();
    }

    public function getCursor(string $domain): ?\DateTimeImmutable
    {
        $watermark = $this->watermarks->findOneByDomain($domain);
        if ($watermark === null) {
            return null;
        }
        $cursor = $watermark->getLastCursor();
        if ($cursor === null || $cursor === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($cursor);
        } catch (\Exception) {
            return null;
        }
    }

    public function getLastSuccessAt(string $domain): ?\DateTimeImmutable
    {
        $watermark = $this->watermarks->findOneByDomain($domain);

        return $watermark?->getLastSuccessAt();
    }
}
