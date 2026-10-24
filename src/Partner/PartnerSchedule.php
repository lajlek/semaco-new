<?php

declare(strict_types=1);

namespace App\Partner;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('partners')]
final readonly class PartnerSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->lockFactory->createLock('app-partner-schedule'))
            ->add(
                RecurringMessage::every('1 minute', new RunCommandMessage('app:partners:process-queue --no-debug')),
                RecurringMessage::every('1 hour', new RunCommandMessage('app:partners:match --exported --no-debug')),
            );
    }
}
