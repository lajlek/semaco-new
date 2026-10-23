<?php

declare(strict_types=1);

namespace App\Partner\Command;

use App\Partner\Fetch\FetchForbiddenException;
use App\Partner\Fetch\PolitePageFetcher;
use App\Partner\Service\PartnerItemStore;
use App\Partner\Service\PartnerParserRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:partners:fetch', description: 'Ohleduplně načte výpis partnera (robots.txt, rozestup požadavků) a uloží položky.')]
final class FetchPartnerListingCommand extends Command
{
    public function __construct(
        private readonly PolitePageFetcher $fetcher,
        private readonly PartnerParserRegistry $parsers,
        private readonly PartnerItemStore $store,
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

        $parser = $this->parsers->get($partner);
        if (!$parser->supportsAutomaticFetching()) {
            $io->error(sprintf('U partnera %s automatické načítání neprovádíme (robots.txt / podmínky). Použij app:partners:import-html.', $partner));

            return Command::FAILURE;
        }

        $queue = [self::normalize($startUrl)];
        $visited = [];
        $totals = ['created' => 0, 'changed' => 0, 'unchanged' => 0];
        $reported = null;

        while ($queue !== [] && count($visited) < $maxPages) {
            $url = array_shift($queue);
            if (isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;

            try {
                $page = $parser->parse($this->fetcher->fetch($url, $partner), $url);
            } catch (FetchForbiddenException $exception) {
                $io->warning($exception->getMessage());

                break;
            }

            $reported ??= $page->totalCount;
            $summary = $this->store->store($partner, $page->items, $url, $dryRun);
            foreach ($summary as $key => $count) {
                $totals[$key] += $count;
            }
            $io->writeln(sprintf('%s: %d položek (nové %d, změněné %d)', $url, count($page->items), $summary['created'], $summary['changed']));

            foreach (array_map(self::normalize(...), $page->pageUrls) as $pageUrl) {
                if (!isset($visited[$pageUrl]) && !in_array($pageUrl, $queue, true)) {
                    $queue[] = $pageUrl;
                }
            }
        }

        $io->success(sprintf(
            'Stránek %d, položek nové %d / změněné %d / beze změny %d%s, nenačtených stránek ve frontě %d.',
            count($visited),
            $totals['created'],
            $totals['changed'],
            $totals['unchanged'],
            $reported !== null ? sprintf(', partner hlásí celkem %d', $reported) : '',
            count($queue),
        ));

        return Command::SUCCESS;
    }

    private static function normalize(string $url): string
    {
        $url = (string) preg_replace('/([?&])page=1(?=&|$)/', '$1', $url);

        return rtrim(str_replace(['?&', '&&'], ['?', '&'], $url), '?&');
    }
}
