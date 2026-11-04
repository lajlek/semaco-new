<?php

declare(strict_types=1);

namespace App\Partner\Command;

use App\Partner\Fetch\PartnerFetchQueue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:partners:continue-categories', description: 'Zařadí další část rozpracovaných kategorií partnerů (nejvýš jeden běh na partnera).')]
final class ContinuePartnerCategoriesCommand extends Command
{
    public function __construct(private readonly PartnerFetchQueue $queue)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Zařazeno běhů: %d', $this->queue->queueContinuations()));

        return Command::SUCCESS;
    }
}
