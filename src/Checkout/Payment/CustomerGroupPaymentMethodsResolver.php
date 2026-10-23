<?php

declare(strict_types=1);

namespace App\Checkout\Payment;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

final readonly class CustomerGroupPaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    public const ALL_METHODS = '*';

    /**
     * @param array<array-key, list<string>|string> $methodsByCustomerGroup customer group code => payment method codes, or '*'
     * @param list<string> $methodsWithoutGroup
     */
    public function __construct(
        private PaymentMethodsResolverInterface $channelBasedResolver,
        private array $methodsByCustomerGroup,
        private array $methodsWithoutGroup,
    ) {
    }

    /** @return PaymentMethodInterface[] */
    public function getSupportedMethods(BasePaymentInterface $subject): array
    {
        $methods = $this->channelBasedResolver->getSupportedMethods($subject);
        $allowed = $this->allowedCodes($subject);
        if (!is_array($allowed)) {
            return $methods;
        }

        return array_values(array_filter(
            $methods,
            static fn (PaymentMethodInterface $method): bool => in_array($method->getCode(), $allowed, true),
        ));
    }

    public function supports(BasePaymentInterface $subject): bool
    {
        return $subject instanceof PaymentInterface && $this->channelBasedResolver->supports($subject);
    }

    /** @return list<string>|string */
    private function allowedCodes(BasePaymentInterface $subject): array|string
    {
        $order = $subject instanceof PaymentInterface ? $subject->getOrder() : null;
        $groupCode = $order instanceof OrderInterface ? $order->getCustomer()?->getGroup()?->getCode() : null;

        if ($groupCode === null) {
            return $this->methodsWithoutGroup;
        }

        $allowed = $this->methodsByCustomerGroup[$groupCode] ?? $this->methodsWithoutGroup;

        return $allowed === self::ALL_METHODS || is_array($allowed) ? $allowed : [$allowed];
    }
}
