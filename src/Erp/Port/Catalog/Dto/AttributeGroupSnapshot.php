<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class AttributeGroupSnapshot
{
    public const TYPE_SELECT = 'select';

    public const TYPE_INTEGER = 'integer';

    /**
     * @param list<AttributeValueSnapshot> $values
     */
    public function __construct(
        public string $externalIdentifier,
        public string $name,
        public string $type,
        public int $position,
        public ?int $filterType,
        public array $values,
    ) {
    }
}
