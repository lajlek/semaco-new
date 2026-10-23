<?php

declare(strict_types=1);

namespace App\Erp\Port\Customer\Dto;

final readonly class SalesRepresentativeSnapshot
{
    public function __construct(
        public string $externalIdentifier,
        public ?string $code,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $phoneNumber,
    ) {
    }

    public function getName(): string
    {
        $name = trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? ''));

        return $name === '' ? $this->externalIdentifier : $name;
    }
}
