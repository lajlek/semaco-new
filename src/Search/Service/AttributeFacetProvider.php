<?php

declare(strict_types=1);

namespace App\Search\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class AttributeFacetProvider
{
    public const ATTRIBUTE_PREFIX = 'attr_group_';

    /** @var array<int, list<array{code: string, name: string, filterType: int|null, values: array<string, string>}>> */
    private array $cache = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return list<array{code: string, name: string, filterType: int|null, values: array<string, string>}> */
    public function forTaxon(TaxonInterface $taxon, string $localeCode): array
    {
        $taxonId = (int) $taxon->getId();
        if (isset($this->cache[$taxonId])) {
            return $this->cache[$taxonId];
        }

        $connection = $this->entityManager->getConnection();
        /** @var list<array{code: string, name: string|null, filter_type: int|string|null, configuration: string|null, json_value: string|null, integer_value: int|string|null}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT a.code, at.name, a.filter_type, a.configuration, av.json_value, av.integer_value
             FROM sylius_product_taxon pt
             JOIN sylius_product p ON p.id = pt.product_id AND p.enabled = 1
             JOIN sylius_product_attribute_value av ON av.product_id = p.id
             JOIN sylius_product_attribute a ON a.id = av.attribute_id AND a.code LIKE :prefix
             LEFT JOIN sylius_product_attribute_translation at ON at.translatable_id = a.id AND at.locale = :locale
             WHERE pt.taxon_id = :taxon
             ORDER BY a.position ASC, a.code ASC',
            ['prefix' => self::ATTRIBUTE_PREFIX . '%', 'locale' => $localeCode, 'taxon' => $taxonId],
        );

        $groups = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            if (!isset($groups[$code])) {
                $configuration = json_decode((string) $row['configuration'], true);
                $groups[$code] = [
                    'code' => $code,
                    'name' => (string) ($row['name'] ?? $code),
                    'filterType' => $row['filter_type'] === null ? null : (int) $row['filter_type'],
                    'values' => [],
                    'choices' => is_array($configuration) && is_array($configuration['choices'] ?? null) ? $configuration['choices'] : [],
                ];
            }

            if ($row['integer_value'] !== null) {
                $groups[$code]['values'][(string) $row['integer_value']] = (string) $row['integer_value'];

                continue;
            }

            $keys = json_decode((string) $row['json_value'], true);
            foreach (is_array($keys) ? $keys : [] as $key) {
                if (!is_string($key)) {
                    continue;
                }
                $choice = $groups[$code]['choices'][$key] ?? null;
                $label = is_array($choice) ? ($choice[$localeCode] ?? null) : null;
                if (is_string($label)) {
                    $groups[$code]['values'][$key] = $label;
                }
            }
        }

        $facets = [];
        foreach ($groups as $group) {
            if ($group['values'] === []) {
                continue;
            }
            $values = $group['values'];
            uasort($values, static fn (string $a, string $b): int => strnatcasecmp(str_replace(',', '.', $a), str_replace(',', '.', $b)));
            $facets[] = ['code' => $group['code'], 'name' => $group['name'], 'filterType' => $group['filterType'], 'values' => $values];
        }

        return $this->cache[$taxonId] = $facets;
    }

    /**
     * @param array<string, list<string>> $selected attribute code => value keys
     *
     * @return list<int>|null product ids matching all groups, null when nothing is selected
     */
    public function matchingProductIds(array $selected): ?array
    {
        $ids = null;
        foreach ($selected as $code => $values) {
            if ($values === [] || !str_starts_with($code, self::ATTRIBUTE_PREFIX)) {
                continue;
            }

            $conditions = [];
            $parameters = ['code' => $code, 'integers' => array_values(array_filter($values, 'is_numeric'))];
            $types = ['integers' => ArrayParameterType::INTEGER];
            foreach ($values as $index => $value) {
                $conditions[] = sprintf('JSON_CONTAINS(av.json_value, :value%d)', $index);
                $parameters['value' . $index] = (string) json_encode($value);
            }
            if ($parameters['integers'] !== []) {
                $conditions[] = 'av.integer_value IN (:integers)';
            } else {
                unset($parameters['integers'], $types['integers']);
            }

            /** @var list<int|string> $groupIds */
            $groupIds = $this->entityManager->getConnection()->fetchFirstColumn(
                sprintf(
                    'SELECT DISTINCT av.product_id FROM sylius_product_attribute_value av
                     JOIN sylius_product_attribute a ON a.id = av.attribute_id AND a.code = :code
                     WHERE %s',
                    implode(' OR ', $conditions),
                ),
                $parameters,
                $types,
            );

            $groupIds = array_map('intval', $groupIds);
            $ids = $ids === null ? $groupIds : array_values(array_intersect($ids, $groupIds));
        }

        return $ids;
    }
}
