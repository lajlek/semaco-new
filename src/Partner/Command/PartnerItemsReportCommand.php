<?php

declare(strict_types=1);

namespace App\Partner\Command;

use App\Partner\Entity\PartnerItem;
use App\Partner\Service\PartnerItemMatcher;
use App\Partner\Service\PartnerItemPreparer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:partners:match', description: 'Znovu spáruje položky partnerů s variantami podle objednacího kódu, připraví návrhy a vypíše přehled.')]
final class PartnerItemsReportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PartnerItemMatcher $matcher,
        private readonly PartnerItemPreparer $preparer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('partner', null, InputOption::VALUE_REQUIRED, 'Jen jeden partner')
            ->addOption('exported', null, InputOption::VALUE_NONE, 'Jen položky exportované do Heliosu (čekající na spárování)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $criteria = is_string($input->getOption('partner')) ? ['partnerCode' => $input->getOption('partner')] : [];
        if ((bool) $input->getOption('exported')) {
            $criteria['workflowState'] = PartnerItem::STATE_EXPORTED;
        }

        $rows = [];
        $families = [];
        foreach ($this->entityManager->getRepository(PartnerItem::class)->findBy($criteria, ['partnerCode' => 'ASC', 'orderCode' => 'ASC']) as $item) {
            $this->matcher->match($item);
            $rows[$item->getPartnerCode()][$item->getMatchStatus()] = ($rows[$item->getPartnerCode()][$item->getMatchStatus()] ?? 0) + 1;
            $families[$item->getPartnerCode()][$item->getFamilyKey() ?? '#' . $item->getOrderCode()] = true;
        }
        $this->entityManager->flush();
        foreach (array_keys($rows) as $partner) {
            if ((bool) $input->getOption('exported')) {
                $this->preparer->prepareFamilies($partner, array_map(strval(...), array_keys($families[$partner])));
            } else {
                $this->preparer->prepare($partner);
            }
        }

        $io->table(
            ['Partner', 'Spárováno', 'Nenalezeno', 'Nejednoznačné'],
            array_map(
                static fn (string $partner, array $counts): array => [
                    $partner,
                    $counts[PartnerItem::MATCH_MATCHED] ?? 0,
                    $counts[PartnerItem::MATCH_UNMATCHED] ?? 0,
                    $counts[PartnerItem::MATCH_AMBIGUOUS] ?? 0,
                ],
                array_keys($rows),
                $rows,
            ),
        );

        return Command::SUCCESS;
    }
}
