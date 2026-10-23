<?php

declare(strict_types=1);

namespace App\Migration\Command;

use App\Erp\Application\ImportResult;
use App\Migration\Media\MediaMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:migrate:media', description: 'Převezme obrázky, dokumenty a loga značek ze starého e-shopu (soubory z lokální kopie public/media).')]
final class MigrateMediaCommand extends Command
{
    public function __construct(private readonly MediaMigrator $mediaMigrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Adresář s kopií starého public/media (nebo public/media/image)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nic nekopíruje ani neukládá')
            ->addOption('warnings', null, InputOption::VALUE_REQUIRED, 'Kolik varování vypsat', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $input->getOption('source');
        if (!is_string($source) || !is_dir($source)) {
            $io->error('Zadej existující adresář --source.');

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $warnings = $input->getOption('warnings');
        $limit = is_numeric($warnings) ? (int) $warnings : 20;

        foreach ([
            $this->mediaMigrator->migrateImages($source, $dryRun),
            $this->mediaMigrator->migrateDocuments($source, $dryRun),
            $this->mediaMigrator->migrateBrandLogos($source, $dryRun),
        ] as $result) {
            $this->report($io, $result, $limit);
        }

        return Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, ImportResult $result, int $limit): void
    {
        $io->writeln(sprintf('<info>%s</info>: nové %d, aktualizované %d, přeskočené %d', $result->domain, $result->getCreated(), $result->getUpdated(), $result->getSkipped()));
        foreach (array_slice($result->getWarnings(), 0, $limit) as $warning) {
            $io->writeln('  ! ' . $warning);
        }
        if (count($result->getWarnings()) > $limit) {
            $io->writeln(sprintf('  … a dalších %d', count($result->getWarnings()) - $limit));
        }
    }
}
