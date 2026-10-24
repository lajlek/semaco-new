<?php

declare(strict_types=1);

namespace App\Partner\Command;

use App\Partner\Fetch\PartnerFetchQueue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:partners:process-queue', description: 'Zpracuje jedno načítání partnera zadané v administraci.')]
final class ProcessPartnerFetchQueueCommand extends Command
{
    public function __construct(private readonly PartnerFetchQueue $queue)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $run = $this->queue->processNext();
        if ($run === null) {
            $io->writeln('Fronta je prázdná.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('%s %s: %s, stránek %d, nové %d, změněné %d%s', $run->getPartnerCode(), $run->getUrl(), $run->getStatus(), $run->getPages(), $run->getItemsCreated(), $run->getItemsChanged(), $run->getMessage() !== null ? ' – ' . $run->getMessage() : ''));

        return Command::SUCCESS;
    }
}
