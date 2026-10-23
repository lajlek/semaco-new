<?php

declare(strict_types=1);

namespace App\Partner\Command;

use App\Partner\Service\PartnerItemStore;
use App\Partner\Service\PartnerParserRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:partners:import-html', description: 'Načte položky partnera z ručně uložené HTML stránky.')]
final class ImportPartnerHtmlCommand extends Command
{
    public function __construct(
        private readonly PartnerParserRegistry $parsers,
        private readonly PartnerItemStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('partner', InputArgument::REQUIRED, 'pokolm | fraisa | brw')
            ->addArgument('file', InputArgument::REQUIRED, 'Uložený HTML soubor')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Původní URL stránky', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nic neukládá');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $partner = $input->getArgument('partner');
        $url = $input->getOption('url');
        if (!is_string($file) || !is_string($partner) || !is_string($url)) {
            return Command::INVALID;
        }

        if (!is_file($file)) {
            $io->error(sprintf('Soubor %s neexistuje.', $file));

            return Command::INVALID;
        }

        $sourceUrl = $url !== '' ? $url : 'file://' . basename($file);
        $page = $this->parsers->get($partner)->parse((string) file_get_contents($file), $sourceUrl);
        $summary = $this->store->store($partner, $page->items, $sourceUrl, (bool) $input->getOption('dry-run'));

        $io->success(sprintf('Položek %d: nové %d, změněné %d, beze změny %d.', count($page->items), $summary['created'], $summary['changed'], $summary['unchanged']));

        return Command::SUCCESS;
    }
}
