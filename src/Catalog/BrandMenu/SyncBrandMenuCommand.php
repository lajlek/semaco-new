<?php

declare(strict_types=1);

namespace App\Catalog\BrandMenu;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:catalog:brand-menu', description: 'Založí kategorie podle výrobců a zařadí do nich produkty.')]
final class SyncBrandMenuCommand extends Command
{
    public function __construct(private readonly BrandMenuSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->synchronizer->sync();
        $output->writeln(sprintf('Vytvořeno: %d, změněno: %d', $result->getCreated(), $result->getUpdated()));

        return Command::SUCCESS;
    }
}
