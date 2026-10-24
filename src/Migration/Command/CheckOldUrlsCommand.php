<?php

declare(strict_types=1);

namespace App\Migration\Command;

use App\Migration\Url\OldShopUrlList;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:migrate:check-urls', description: 'Ověří, že všechny veřejné URL starého e-shopu na novém webu fungují (200 nebo přesměrování na 200).')]
final class CheckOldUrlsCommand extends Command
{
    private const MAX_REDIRECTS = 3;

    public function __construct(
        private readonly OldShopUrlList $urlList,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Adresa nového webu, např. https://eshop.semaco.cz')
            ->addOption('concurrency', null, InputOption::VALUE_REQUIRED, 'Kolik požadavků najednou', '4')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Ověřit jen prvních N adres');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseUrl = $input->getOption('base-url');
        $baseUrl = is_string($baseUrl) ? rtrim($baseUrl, '/') : '';
        if (preg_match('#^https?://[^/]+$#', $baseUrl) !== 1) {
            $io->error('Zadej --base-url ve tvaru https://host.');

            return Command::INVALID;
        }
        $concurrency = $input->getOption('concurrency');
        $concurrency = is_numeric($concurrency) ? max(1, (int) $concurrency) : 4;
        $paths = $this->urlList->paths();
        if (is_numeric($input->getOption('limit'))) {
            $paths = array_slice($paths, 0, (int) $input->getOption('limit'));
        }

        $failures = [];
        $redirected = 0;
        $io->progressStart(count($paths));
        foreach (array_chunk($paths, $concurrency) as $chunk) {
            foreach ($this->checkAll($baseUrl, $chunk) as $path => [$ok, $hops, $detail]) {
                if (!$ok) {
                    $failures[] = [$path, $detail];
                } elseif ($hops > 0) {
                    ++$redirected;
                }
                $io->progressAdvance();
            }
        }
        $io->progressFinish();

        $io->writeln(sprintf('Ověřeno %d adres: v pořádku %d (z toho přesměrovaných %d), chyb %d.', count($paths), count($paths) - count($failures), $redirected, count($failures)));
        if ($failures !== []) {
            $io->table(['Stará adresa', 'Výsledek'], $failures);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, array{bool, int, string}>
     */
    private function checkAll(string $baseUrl, array $paths): array
    {
        $pending = [];
        foreach ($paths as $path) {
            $pending[$path] = ['url' => $baseUrl . $path, 'hops' => 0];
        }

        $results = [];
        while ($pending !== []) {
            $responses = [];
            foreach ($pending as $path => $state) {
                $responses[$path] = $this->httpClient->request('GET', $state['url'], ['max_redirects' => 0, 'timeout' => 60]);
            }

            $next = [];
            foreach ($responses as $path => $response) {
                try {
                    $status = $response->getStatusCode();
                    $location = $response->getHeaders(false)['location'][0] ?? null;
                    $response->cancel();
                } catch (TransportExceptionInterface $exception) {
                    $results[$path] = [false, $pending[$path]['hops'], $exception->getMessage()];

                    continue;
                }

                $hops = $pending[$path]['hops'];
                if ($status >= 300 && $status < 400 && $location !== null && $hops < self::MAX_REDIRECTS) {
                    $target = str_starts_with($location, '/') ? $baseUrl . $location : $location;
                    if (!str_starts_with($target, $baseUrl . '/')) {
                        $results[$path] = [false, $hops + 1, sprintf('%d → mimo web: %s', $status, $location)];

                        continue;
                    }
                    $next[$path] = ['url' => $target, 'hops' => $hops + 1];

                    continue;
                }

                $results[$path] = $status === 200
                    ? [true, $hops, '']
                    : [false, $hops, sprintf('%d%s', $status, $hops > 0 ? ' (po přesměrování na ' . substr($pending[$path]['url'], strlen($baseUrl)) . ')' : '')];
            }
            $pending = $next;
        }

        return $results;
    }
}
