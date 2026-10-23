<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Command;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\HeliosWriteForbiddenException;
use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\ReadOnlyStatementGuard;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:erp:inspect', description: 'Diagnostika: spustí SELECT nad Heliosem.')]
final class InspectHeliosCommand extends Command
{
    public function __construct(
        private readonly HeliosConnectionFactory $connections,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::REQUIRED, 'SELECT dotaz');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = $input->getArgument('query');
        if (!is_string($query)) {
            return Command::INVALID;
        }

        try {
            ReadOnlyStatementGuard::assertReadOnly($query);
        } catch (HeliosWriteForbiddenException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $result = $this->connections->getConnection()->executeQuery($query);

        $rows = 0;
        while (($row = $result->fetchAssociative()) !== false) {
            if (++$rows === 1) {
                $output->writeln(implode(' | ', array_keys($row)));
                $output->writeln(str_repeat('-', 80));
            }

            $output->writeln(implode(' | ', array_map(
                static fn (mixed $value): string => match (true) {
                    $value === null => 'NULL',
                    is_scalar($value) => (string) $value,
                    default => get_debug_type($value),
                },
                $row,
            )));
        }

        $output->writeln(sprintf('<info>(%d řádků)</info>', $rows));

        return Command::SUCCESS;
    }
}
