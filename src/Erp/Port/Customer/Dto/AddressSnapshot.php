<?php

declare(strict_types=1);

namespace App\Erp\Port\Customer\Dto;

final readonly class AddressSnapshot
{
    public function __construct(
        public string $externalIdentifier,
        public ?string $company,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $street,
        public ?string $city,
        public ?string $postcode,
        public ?string $countryCode,
        public ?string $phoneNumber,
    ) {
    }
}
