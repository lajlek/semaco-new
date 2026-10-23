<?php

declare(strict_types=1);

namespace App\Erp\Port\Order\Dto;

final readonly class OrderParty
{
    public function __construct(
        public ?string $company,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $street,
        public ?string $city,
        public ?string $postcode,
        public ?string $countryCode,
        public ?string $phone,
        public ?string $email,
        public ?string $ico,
        public ?string $dic,
    ) {
    }
}
