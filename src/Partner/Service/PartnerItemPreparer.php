<?php

declare(strict_types=1);

namespace App\Partner\Service;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Partner\Entity\PartnerItem;
use App\Partner\Repository\PartnerItemRepository;
use App\Partner\Rules\PartnerRule;
use App\Partner\Rules\PartnerRules;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type ItemPlan array{item: PartnerItem, matched: bool, name: ?string, templated: bool, productName: ?string, taxonCode: ?string, options: array<string, array{value: string, source: string}>, issues: list<array{code: string, message: string, blocking: bool}>}
 */
final class PartnerItemPreparer
{
    public const SOURCE_PARTNER = 'partner';

    public const SOURCE_FAMILY = 'family';

    public const SOURCE_MANUAL = 'manual';

    /** @var array<string, string>|null */
    private ?array $optionNames = null;

    /** @param RepositoryInterface<ProductOptionInterface> $optionRepository */
    public function __construct(
        #[Autowire(service: 'app.repository.partner_item')]
        private readonly PartnerItemRepository $partnerItemRepository,
        #[Autowire(service: 'sylius.repository.product_option')]
        private readonly RepositoryInterface $optionRepository,
        private readonly PartnerRules $rules,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%app.erp.locale_code%')]
        private readonly string $localeCode,
    ) {
    }

    /** @return array<string, string> option code => Czech name */
    public function optionNames(): array
    {
        if ($this->optionNames === null) {
            $this->optionNames = [];
            foreach ($this->optionRepository->findAll() as $option) {
                $this->optionNames[(string) $option->getCode()] = (string) $option->getTranslation($this->localeCode)->getName();
            }
        }

        return $this->optionNames;
    }

    public function prepare(string $partnerCode, ?string $onlyFamilyKey = null): int
    {
        $rule = $this->rules->get($partnerCode);
        $families = [];
        foreach ($this->partnerItemRepository->findForPreparation($partnerCode) as $item) {
            $familyKey = $rule->familyKey($item->getOrderCode(), $item->getFamilyCode());
            $families[$familyKey ?? '#' . $item->getOrderCode()][] = $item;
        }

        $knownFamilies = $this->knownFamilies($families);
        $count = 0;
        foreach ($families as $familyKey => $members) {
            if ($onlyFamilyKey !== null && (string) $familyKey !== $onlyFamilyKey) {
                continue;
            }

            $products = [];
            foreach ($members as $member) {
                $product = $member->getMatchedVariant()?->getProduct();
                if ($product instanceof Product) {
                    $products[(int) $product->getId()] = $product;
                }
            }

            $product = count($products) === 1 ? reset($products) : null;
            $grouping = match (true) {
                count($products) > 1 => PartnerItem::GROUPING_AMBIGUOUS,
                $product !== null => PartnerItem::GROUPING_EXISTING,
                default => PartnerItem::GROUPING_NEW,
            };
            $familyValues = $product !== null ? $this->sharedOptionValues($product) : [];
            $required = $product !== null
                ? array_values(array_map(static fn (ProductOptionInterface $option): string => (string) $option->getCode(), $product->getOptions()->toArray()))
                : array_values($rule->options);

            $similar = $grouping === PartnerItem::GROUPING_NEW ? self::similarFamily($knownFamilies, (string) $members[0]->getName()) : null;
            $plans = [];
            foreach ($members as $item) {
                $plans[] = $this->planItem($item, $rule, $grouping, $familyValues, $required, $similar);
            }
            [$renamed, $duplicates] = $this->disambiguateNames($plans, $rule);

            foreach ($plans as $index => $plan) {
                $issues = $plan['issues'];
                if (isset($duplicates[$index])) {
                    $issues[] = self::issue('duplicate_name', sprintf('Název „%s“ má v rodině více variant, zadejte název ručně', $duplicates[$index]), true);
                }
                $plan['item']->applyPreparation(
                    str_starts_with((string) $familyKey, '#') ? null : (string) $familyKey,
                    $grouping,
                    $product,
                    $renamed[$index] ?? $plan['name'],
                    $plan['productName'],
                    $plan['taxonCode'],
                    $plan['options'],
                    $issues,
                );
                ++$count;
            }
        }
        $this->entityManager->flush();

        return $count;
    }

    /**
     * @param array<string, string> $familyValues
     * @param list<string> $required
     * @param array{name: string, product_name: string, taxon_code: ?string}|null $similar
     *
     * @return ItemPlan
     */
    private function planItem(PartnerItem $item, PartnerRule $rule, string $grouping, array $familyValues, array $required, ?array $similar): array
    {
        $overrides = $item->getOverrides();
        $source = $rule->sourceValues([...$item->getParameters(), ...$item->getDetailParameters()]);

        $options = [];
        foreach ($rule->options as $parameter => $code) {
            $value = self::number($source[$parameter] ?? null);
            if ($value !== null) {
                $options[$code] = ['value' => $value, 'source' => self::SOURCE_PARTNER];
            }
        }
        foreach ($familyValues as $code => $value) {
            $options[$code] ??= ['value' => $value, 'source' => self::SOURCE_FAMILY];
        }
        foreach ($overrides['options'] ?? [] as $code => $value) {
            if (trim($value) !== '') {
                $options[$code] = ['value' => trim($value), 'source' => self::SOURCE_MANUAL];
            }
        }

        $issues = [];
        $productName = null;
        $taxonCode = null;
        $names = $this->optionNames();
        $matchedVariant = $item->getMatchedVariant();
        $matched = $item->getMatchStatus() === PartnerItem::MATCH_MATCHED;
        if ($matched && $matchedVariant instanceof ProductVariant) {
            $actual = $this->variantOptionValues($matchedVariant);
            foreach ($options as $code => $option) {
                if ($option['source'] === self::SOURCE_PARTNER && isset($actual[$code]) && !self::sameValue($option['value'], $actual[$code])) {
                    $issues[] = self::issue('conflict', sprintf('Rozdíl %s: partner %s, Semaco %s', $names[$code] ?? $code, $option['value'], $actual[$code]), false);
                }
            }
        } else {
            if ($item->getMatchStatus() === PartnerItem::MATCH_AMBIGUOUS) {
                $issues[] = self::issue('ambiguous_code', 'Objednací kód odpovídá více variantám Semaco', true);
            }
            if ($grouping === PartnerItem::GROUPING_AMBIGUOUS) {
                $issues[] = self::issue('ambiguous_family', 'Rodina patří k více produktům Semaco, vyberte hlavní produkt', true);
            }
            foreach ($required as $code) {
                if (!isset($options[$code])) {
                    $issues[] = self::issue('missing', sprintf('Chybí %s', $names[$code] ?? $code), true);
                }
            }
            if ($grouping === PartnerItem::GROUPING_NEW) {
                $productName = trim($overrides['product_name'] ?? '') ?: $similar['product_name'] ?? null;
                $taxonCode = trim($overrides['taxon_code'] ?? '') ?: $similar['taxon_code'] ?? null;
                if ($productName === null) {
                    $issues[] = self::issue('missing', 'Chybí český název nového hlavního produktu', true);
                }
                if ($taxonCode === null) {
                    $issues[] = self::issue('missing', 'Chybí kategorie nového hlavního produktu', true);
                }
                if ($similar !== null && (trim($overrides['product_name'] ?? '') === '' || trim($overrides['taxon_code'] ?? '') === '')) {
                    $issues[] = self::issue('derived', sprintf('Název a kategorie navrženy podle rodiny „%s“, zkontrolujte', $similar['name']), false);
                }
            }
            if ($item->getPrice() === null) {
                $issues[] = self::issue('price', 'Cena od partnera není k dispozici, doplní se v Heliosu', false);
            }
        }

        $name = trim($overrides['name'] ?? '');
        $templated = $name === '';
        if ($templated) {
            $name = (string) preg_replace_callback(
                '/\{(\w+)\}/',
                static fn (array $match): string => $options[$match[1]]['value'] ?? '{?}',
                $rule->variantNameTemplate,
            );
        }
        if (str_contains($name, '{?}')) {
            $issues[] = self::issue('missing', 'Název varianty nelze sestavit, chybí parametr', !$matched);
            $name = null;
        }

        return [
            'item' => $item,
            'matched' => $matched,
            'name' => $name,
            'templated' => $templated,
            'productName' => $productName,
            'taxonCode' => $taxonCode,
            'options' => $options,
            'issues' => $issues,
        ];
    }

    /**
     * @param list<ItemPlan> $plans
     *
     * @return array{array<int, string>, array<int, string>} new names and unresolved duplicate names by plan index
     */
    private function disambiguateNames(array $plans, PartnerRule $rule): array
    {
        $byName = [];
        foreach ($plans as $index => $plan) {
            if (!$plan['matched'] && $plan['templated'] && $plan['name'] !== null) {
                $byName[$plan['name']][] = $index;
            }
        }

        $renamed = [];
        $duplicates = [];
        foreach ($byName as $name => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $names = array_fill(0, count($indexes), (string) $name);
            $resolved = false;
            foreach ($rule->options as $parameter => $code) {
                $values = array_map(static fn (int $index): string => $plans[$index]['options'][$code]['value'] ?? '', $indexes);
                if (in_array('', $values, true) || count(array_unique($values)) === 1) {
                    continue;
                }

                $label = strtoupper(trim((string) preg_replace('/\s*\[.*]$/', '', $parameter)));
                $candidate = array_map(static fn (string $current, string $value): string => sprintf('%s %s=%s', $current, $label, $value), $names, $values);
                if (count(array_unique($candidate)) <= count(array_unique($names))) {
                    continue;
                }
                $names = $candidate;
                if (count(array_unique($names)) === count($names)) {
                    $resolved = true;

                    break;
                }
            }

            foreach ($indexes as $position => $index) {
                if ($resolved) {
                    $renamed[$index] = $names[$position];
                } else {
                    $duplicates[$index] = (string) $name;
                }
            }
        }

        return [$renamed, $duplicates];
    }

    /**
     * @param array<string, list<PartnerItem>> $families
     *
     * @return array<string, array{name: string, product_name: string, taxon_code: ?string}> lowercased partner name => Semaco product
     */
    private function knownFamilies(array $families): array
    {
        $known = [];
        foreach ($families as $members) {
            foreach ($members as $member) {
                $product = $member->getMatchedVariant()?->getProduct();
                $name = trim((string) $member->getName());
                if ($product instanceof Product && $name !== '') {
                    $known[mb_strtolower($name)] ??= ['name' => $name, 'product_name' => (string) $product->getName(), 'taxon_code' => $product->getMainTaxon()?->getCode()];
                }
            }
        }

        return $known;
    }

    /**
     * @param array<string, array{name: string, product_name: string, taxon_code: ?string}> $known
     *
     * @return array{name: string, product_name: string, taxon_code: ?string}|null
     */
    private static function similarFamily(array $known, string $name): ?array
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }
        if (isset($known[$name])) {
            return $known[$name];
        }

        $best = null;
        foreach ($known as $knownName => $family) {
            if (str_starts_with($name, $knownName . ' ') && ($best === null || mb_strlen($knownName) > mb_strlen($best))) {
                $best = $knownName;
            }
        }

        return $best !== null ? $known[$best] : null;
    }

    /** @return array<string, string> */
    private function sharedOptionValues(Product $product): array
    {
        $shared = null;
        foreach ($product->getVariants() as $variant) {
            if (!$variant instanceof ProductVariant || !$variant->isEnabled()) {
                continue;
            }
            $values = $this->variantOptionValues($variant);
            $shared = $shared === null ? $values : array_intersect_assoc($shared, $values);
        }

        return $shared ?? [];
    }

    /** @return array<string, string> */
    private function variantOptionValues(ProductVariantInterface $variant): array
    {
        $values = [];
        foreach ($variant->getOptionValues() as $optionValue) {
            $values[(string) $optionValue->getOptionCode()] = (string) $optionValue->getTranslation($this->localeCode)->getValue();
        }

        return $values;
    }

    public static function number(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || in_array($value, ['-', '—', '–'], true)) {
            return null;
        }

        if (preg_match('#^\+/-\s*(\d+(?:[.,]\d+)?)$#', $value, $match) === 1) {
            return '+/-' . self::number($match[1]);
        }

        if (preg_match('/^\d+(?:[.,]\d+)?$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
            if (str_contains($value, '.')) {
                $value = rtrim(rtrim($value, '0'), '.');
            }

            return str_replace('.', ',', $value);
        }

        return $value;
    }

    private static function sameValue(string $partner, string $semaco): bool
    {
        $a = str_replace(',', '.', $partner);
        $b = str_replace(',', '.', trim($semaco));

        return is_numeric($a) && is_numeric($b) ? abs((float) $a - (float) $b) < 0.0001 : mb_strtolower($a) === mb_strtolower($b);
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private static function issue(string $code, string $message, bool $blocking): array
    {
        return ['code' => $code, 'message' => $message, 'blocking' => $blocking];
    }
}
