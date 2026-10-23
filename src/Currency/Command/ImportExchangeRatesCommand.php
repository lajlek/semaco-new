<?php

declare(strict_types=1);

namespace App\Currency\Command;

use App\Currency\Service\ExchangeRateUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:exchange-rates:import', description: 'Načte denní kurzy ČNB do kurzů e-shopu.')]
final class ImportExchangeRatesCommand extends Command
{
    public function __construct(private readonly ExchangeRateUpdater $updater)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updated = $this->updater->update();
        if ($updated === []) {
            $output->writeln('<error>Žádný kurz nebyl aktualizován.</error>');

            return Command::FAILURE;
        }

        foreach ($updated as $pair => $ratio) {
            $output->writeln(sprintf('%s = %s', $pair, $ratio));
        }

        return Command::SUCCESS;
    }
}
