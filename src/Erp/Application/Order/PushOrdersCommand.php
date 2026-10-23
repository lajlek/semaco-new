<?php

declare(strict_types=1);

namespace App\Erp\Application\Order;

use App\Entity\Order\Order;
use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\HeliosWriteForbiddenException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:erp:push-orders', description: 'Předá dokončené objednávky do ERP (Helios). Bez povoleného zápisu nic neodešle.')]
final class PushOrdersCommand extends Command
{
    public function __construct(
        private readonly OrderPushService $pushService,
        private readonly OrderPushRequestBuilder $requestBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Jen objednávka s daným číslem')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Jen vypíše, co by se poslalo')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nejvýš objednávek', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $number = $input->getOption('order');
        $limit = $input->getOption('limit');

        $orders = is_string($number)
            ? array_filter([$this->entityManager->getRepository(Order::class)->findOneBy(['number' => $number])])
            : $this->pushService->pendingOrders(is_numeric($limit) ? (int) $limit : 20);

        if ($orders === []) {
            $io->writeln('Žádné objednávky k předání.');

            return Command::SUCCESS;
        }

        foreach ($orders as $order) {
            if ($input->getOption('dry-run')) {
                $io->writeln((string) json_encode($this->requestBuilder->build($order), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

                continue;
            }

            try {
                $io->writeln(sprintf('%s → Helios %s', (string) $order->getNumber(), $this->pushService->push($order)));
            } catch (HeliosWriteForbiddenException) {
                $io->warning('Zápis do Heliosu je vypnutý (HELIOS_WRITE_ENABLED=0), objednávky čekají.');

                return Command::SUCCESS;
            } catch (\Throwable $exception) {
                $io->error(sprintf('%s: %s', (string) $order->getNumber(), $exception->getMessage()));
            }
        }

        return Command::SUCCESS;
    }
}
