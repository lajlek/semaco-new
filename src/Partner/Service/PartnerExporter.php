<?php

declare(strict_types=1);

namespace App\Partner\Service;

use App\Partner\Entity\PartnerExportBatch;
use App\Partner\Entity\PartnerItem;
use App\Partner\Rules\PartnerRules;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PartnerExporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PartnerItemPreparer $preparer,
        private PartnerRules $rules,
    ) {
    }

    /** @param iterable<PartnerItem> $items */
    public function export(iterable $items, string $createdBy): ?PartnerExportBatch
    {
        $ready = [];
        foreach ($items as $item) {
            if ($item->getWorkflowState() === PartnerItem::STATE_READY) {
                $ready[] = $item;
            }
        }
        if ($ready === []) {
            return null;
        }

        $batch = new PartnerExportBatch($createdBy, count($ready), $this->csv($ready));
        $this->entityManager->persist($batch);
        foreach ($ready as $item) {
            $item->markExported($batch);
        }
        $this->entityManager->flush();

        return $batch;
    }

    /** @param list<PartnerItem> $items */
    private function csv(array $items): string
    {
        $names = $this->preparer->optionNames();
        $optionCodes = [];
        foreach ($items as $item) {
            foreach (array_keys($item->getPreparedOptions()) as $code) {
                $optionCodes[$code] = $names[$code] ?? $code;
            }
        }
        asort($optionCodes);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Nelze vytvořit export.');
        }
        fwrite($stream, "\u{FEFF}");
        fputcsv($stream, [
            'Objednací kód', 'Partner', 'Značka', 'Skupina zboží', 'Hlavní produkt – kód', 'Hlavní produkt – název', 'Nový hlavní produkt',
            'Kategorie', 'Název varianty', 'Název u partnera', 'Cena', 'Měna', 'Zdroj', ...array_values($optionCodes),
        ], ';', '"', '');

        foreach ($items as $item) {
            $product = $item->getProposedProduct();
            $options = $item->getPreparedOptions();
            fputcsv($stream, [
                $item->getOrderCode(),
                $this->rules->get($item->getPartnerCode())->label,
                $product?->getBrand()?->getName() ?? $this->rules->get($item->getPartnerCode())->brandName,
                $product?->getSkupzbo() ?? '',
                $product?->getCode() ?? '',
                $product?->getName() ?? '',
                $product === null ? ($item->getPreparedProductName() ?? '') : '',
                $product?->getMainTaxon()?->getCode() ?? $item->getPreparedTaxonCode() ?? '',
                $item->getPreparedName() ?? '',
                trim(($item->getName() ?? '') . ' ' . ($item->getSubtitle() ?? '')),
                $item->getPrice() ?? '',
                $item->getCurrency() ?? '',
                $item->getDetailUrl() ?? $item->getSourceUrl() ?? '',
                ...array_map(static fn (string $code): string => $options[$code]['value'] ?? '', array_keys($optionCodes)),
            ], ';', '"', '');
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
