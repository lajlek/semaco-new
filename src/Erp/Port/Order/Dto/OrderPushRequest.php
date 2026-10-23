<?php

declare(strict_types=1);

namespace App\Erp\Port\Order\Dto;

final readonly class OrderPushRequest
{
    /** @param list<OrderLine> $lines */
    public function __construct(
        public string $orderNumber,
        public \DateTimeImmutable $completedAt,
        public string $shopExternalIdentifier,
        public string $currencyCode,
        public float $total,
        public ?string $notes,
        public ?string $organizationExternalIdentifier,
        public ?string $deliveryAddressExternalIdentifier,
        public ?string $contactPersonExternalIdentifier,
        public ?string $paymentMethod,
        public ?string $shippingMethod,
        public ?OrderParty $guestBilling,
        public ?OrderParty $guestShipping,
        public array $lines,
    ) {
    }
}
