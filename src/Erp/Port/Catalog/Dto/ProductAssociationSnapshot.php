<?php

declare(strict_types=1);

namespace App\Erp\Port\Catalog\Dto;

final readonly class ProductAssociationSnapshot
{
    /** @param list<string> $targetExternalIdentifiers in display order */
    public function __construct(
        public string $ownerExternalIdentifier,
        public string $typeCode,
        public array $targetExternalIdentifiers,
    ) {
    }
}
