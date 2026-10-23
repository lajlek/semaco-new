<?php

declare(strict_types=1);

namespace App\Erp\Port\Customer\Dto;

final readonly class OrganizationSnapshot
{
    /**
     * @param list<AddressSnapshot> $deliveryAddresses
     */
    public function __construct(
        public string $externalIdentifier,
        public ?string $orgNumber,
        public string $name,
        public ?string $ico,
        public ?string $dic,
        public ?string $bankAccount,
        public AddressSnapshot $address,
        public ?string $priceLevelExternalIdentifier,
        public ?string $customerType,
        public bool $freeShipping,
        public ?string $salesRepresentativeCode,
        public array $deliveryAddresses,
        public \DateTimeImmutable $modifiedAt,
    ) {
    }
}
