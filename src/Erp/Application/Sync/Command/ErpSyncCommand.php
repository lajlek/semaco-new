<?php

declare(strict_types=1);

namespace App\Erp\Application\Sync\Command;

use App\Erp\Application\ImportResult;
use App\Erp\Application\Sync\CatalogSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:erp:sync', description: 'Načte katalog, ceny a sklad z ERP (Helios).')]
final class ErpSyncCommand extends Command
{
    public function __construct(
        private readonly CatalogSyncService $catalogSync,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('full', null, InputOption::VALUE_NONE, 'Ignoruje watermarky a načte vše.')
            ->addOption('step', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Jen vybrané kroky: ' . implode(', ', CatalogSyncService::STEPS))
            ->addOption('product', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Jen produkty s daným Helios id (master).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $lock = $this->lockFactory->createLock('app-erp-sync', 3600);
        if (!$lock->acquire()) {
            $io->warning('Synchronizace už běží.');

            return Command::SUCCESS;
        }

        $report = static function (ImportResult $result) use ($io): void {
            $io->writeln(sprintf(
                '<info>%s</info>: nové %d, aktualizované %d, přeskočené %d',
                $result->domain,
                $result->getCreated(),
                $result->getUpdated(),
                $result->getSkipped(),
            ));
            foreach (array_slice($result->getWarnings(), 0, 20) as $warning) {
                $io->writeln('  ! ' . $warning);
            }
        };

        try {
            /** @var list<string> $products */
            $products = $input->getOption('product');
            if ($products !== []) {
                $this->catalogSync->syncProducts($products, $report);

                return Command::SUCCESS;
            }

            /** @var list<string> $steps */
            $steps = $input->getOption('step');
            $steps = $steps === [] ? CatalogSyncService::STEPS : $steps;
            $unknown = array_diff($steps, CatalogSyncService::STEPS);
            if ($unknown !== []) {
                $io->error('Neznámé kroky: ' . implode(', ', $unknown));

                return Command::INVALID;
            }

            $this->catalogSync->sync($steps, (bool) $input->getOption('full'), $report);
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
