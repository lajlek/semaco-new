<?php

declare(strict_types=1);

namespace App\Partner\Command;

use App\Partner\Fetch\FetchForbiddenException;
use App\Partner\Fetch\PartnerListingCrawler;
use App\Partner\Service\PartnerItemPreparer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:partners:fetch', description: 'Ohleduplně načte výpis partnera (robots.txt, rozestup požadavků) a uloží položky.')]
final class FetchPartnerListingCommand extends Command
{
    public function __construct(
        private readonly PartnerListingCrawler $crawler,
        private readonly PartnerItemPreparer $preparer,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('partner', InputArgument::REQUIRED, 'fraisa | brw')
            ->addArgument('url', InputArgument::REQUIRED, 'URL kategorie nebo rodiny')
            ->addOption('max-pages', null, InputOption::VALUE_REQUIRED, 'Nejvýš stránek výpisu', '1')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nic neukládá');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $partner = $input->getArgument('partner');
        $startUrl = $input->getArgument('url');
        if (!is_string($partner) || !is_string($startUrl)) {
            return Command::INVALID;
        }
        $maxPagesOption = $input->getOption('max-pages');
        $maxPages = max(1, is_numeric($maxPagesOption) ? (int) $maxPagesOption : 1);
        $dryRun = (bool) $input->getOption('dry-run');

        $lock = $this->lockFactory->createLock('app-partner-fetch', null);
        if (!$lock->acquire()) {
            $io->error('Jiné načítání partnera právě běží, zkus to později.');

            return Command::FAILURE;
        }

        try {
            $result = $this->crawler->crawl(
                $partner,
                $startUrl,
                $maxPages,
                $dryRun,
                static fn (string $url, int $count, array $summary) => $io->writeln(sprintf('%s: %d položek (nové %d, změněné %d)', $url, $count, $summary['created'], $summary['changed'])),
            );
        } catch (FetchForbiddenException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            $lock->release();
        }

        if ($result['stopped'] !== null) {
            $io->warning($result['stopped']);
        }
        if (!$dryRun) {
            $this->preparer->prepare($partner);
        }

        $io->success(sprintf(
            'Stránek %d, položek nové %d / změněné %d / beze změny %d%s, nenačtených stránek ve frontě %d.',
            $result['pages'],
            $result['created'],
            $result['changed'],
            $result['unchanged'],
            $result['reported'] !== null ? sprintf(', partner hlásí celkem %d', $result['reported']) : '',
            $result['queued'],
        ));

        return Command::SUCCESS;
    }
}
