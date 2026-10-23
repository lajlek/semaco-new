<?php

declare(strict_types=1);

namespace App\Migration\Command;

use App\Migration\Step\MigrationStepInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'app:migrate:old', description: 'Převezme data ze starého e-shopu (databáze OLD_SHOP_DATABASE_URL).')]
final class MigrateOldShopCommand extends Command
{
    /** @param iterable<MigrationStepInterface> $steps */
    public function __construct(
        #[AutowireIterator('app.old_shop_migration_step')]
        private readonly iterable $steps,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('step', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Jen vybrané kroky')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nic nezapisuje, jen vypíše výsledek');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $selected */
        $selected = $input->getOption('step');

        foreach ($this->steps as $step) {
            if ($selected !== [] && !in_array($step->name(), $selected, true)) {
                continue;
            }

            $result = $step->run((bool) $input->getOption('dry-run'));
            $io->writeln(sprintf('<info>%s</info>: aktualizované %d, přeskočené %d', $result->domain, $result->getUpdated() + $result->getCreated(), $result->getSkipped()));
            foreach ($result->getWarnings() as $warning) {
                $io->writeln('  ! ' . $warning);
            }
        }

        return Command::SUCCESS;
    }
}
