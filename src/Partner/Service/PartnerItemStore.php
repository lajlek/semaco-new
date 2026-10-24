<?php

declare(strict_types=1);

namespace App\Partner\Service;

use App\Partner\Dto\ParsedItem;
use App\Partner\Entity\PartnerItem;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PartnerItemStore
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PartnerItemMatcher $matcher,
    ) {
    }

    /**
     * @param list<ParsedItem> $parsedItems
     *
     * @return array{created: int, changed: int, unchanged: int}
     */
    public function store(string $partnerCode, array $parsedItems, string $sourceUrl, bool $dryRun): array
    {
        $summary = ['created' => 0, 'changed' => 0, 'unchanged' => 0];
        $repository = $this->entityManager->getRepository(PartnerItem::class);

        foreach ($parsedItems as $parsed) {
            $item = $repository->findOneBy(['partnerCode' => $partnerCode, 'orderCode' => $parsed->orderCode]);
            $isNew = !$item instanceof PartnerItem;
            if ($isNew) {
                $item = new PartnerItem($partnerCode, $parsed->orderCode);
                $this->entityManager->persist($item);
            }

            $changed = $item->updateContent(
                $parsed->familyCode,
                $parsed->name,
                $parsed->subtitle,
                $sourceUrl,
                $parsed->detailUrl,
                $parsed->parameters,
                $parsed->imageUrls,
                $parsed->price,
                $parsed->currency,
                $parsed->availability,
                self::hash($parsed),
                $parsed->dataUrl,
            );
            $this->matcher->match($item);

            ++$summary[$isNew ? 'created' : ($changed ? 'changed' : 'unchanged')];
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $summary;
    }

    private static function hash(ParsedItem $item): string
    {
        return hash('sha256', (string) json_encode([
            $item->familyCode, $item->name, $item->subtitle, $item->detailUrl, $item->parameters,
            $item->imageUrls, $item->price, $item->currency, $item->availability,
        ]));
    }
}
