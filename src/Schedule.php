<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
final readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->lockFactory->createLock('app-schedule'))
            ->add(
                RecurringMessage::every('10 minutes', new RunCommandMessage('app:erp:sync --no-debug')),
                RecurringMessage::cron('50 14 * * 1-5', new RunCommandMessage('app:exchange-rates:import'), new \DateTimeZone('Europe/Prague')),
            );
    }
}
