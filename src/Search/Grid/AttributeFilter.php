<?php

declare(strict_types=1);

namespace App\Search\Grid;

use App\Search\Service\AttributeFacetProvider;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.grid_filter', ['type' => 'semaco_attributes', 'form_type' => AttributeFilterType::class])]
final readonly class AttributeFilter implements FilterInterface
{
    public function __construct(private AttributeFacetProvider $facetProvider)
    {
    }

    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (!is_array($data)) {
            return;
        }

        $selected = [];
        foreach ($data as $code => $values) {
            $values = array_values(array_filter(is_array($values) ? $values : [$values], static fn (mixed $value): bool => is_string($value) && $value !== ''));
            if (is_string($code) && $values !== []) {
                $selected[$code] = $values;
            }
        }

        $ids = $this->facetProvider->matchingProductIds($selected);
        if ($ids === null) {
            return;
        }

        $dataSource->restrict($dataSource->getExpressionBuilder()->in('id', $ids === [] ? [0] : $ids));
    }
}
