<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\Checkout\Payment\CustomerGroupPaymentMethodsResolver;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerGroup;
use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

final class CustomerGroupPaymentMethodsResolverTest extends TestCase
{
    public function testBankTransferCustomersGetOnlyBankTransfer(): void
    {
        self::assertSame(['10'], $this->codesFor('1'));
    }

    public function testPrepaymentCustomersGetOnlyPrepayment(): void
    {
        self::assertSame(['12'], $this->codesFor('2'));
    }

    public function testSalesRepresentativesGetAllMethods(): void
    {
        self::assertSame(['10', '12'], $this->codesFor('sales'));
    }

    public function testCustomersWithoutGroupOrUnknownGroupGetPrepayment(): void
    {
        self::assertSame(['12'], $this->codesFor(null));
        self::assertSame(['12'], $this->codesFor('group_1'));
    }

    /** @return list<string|null> */
    private function codesFor(?string $groupCode): array
    {
        $resolver = new CustomerGroupPaymentMethodsResolver(
            $this->channelResolver([$this->method('10'), $this->method('12')]),
            ['1' => ['10'], '2' => ['12'], 'sales' => '*'],
            ['12'],
        );

        $customer = new Customer();
        if ($groupCode !== null) {
            $group = new CustomerGroup();
            $group->setCode($groupCode);
            $customer->setGroup($group);
        }

        $order = new Order();
        $order->setCustomer($customer);
        $payment = new Payment();
        $order->addPayment($payment);

        return array_values(array_map(
            static fn (PaymentMethodInterface $method): ?string => $method->getCode(),
            $resolver->getSupportedMethods($payment),
        ));
    }

    private function method(string $code): PaymentMethod
    {
        $method = new PaymentMethod();
        $method->setCode($code);

        return $method;
    }

    /** @param list<PaymentMethodInterface> $methods */
    private function channelResolver(array $methods): PaymentMethodsResolverInterface
    {
        return new class($methods) implements PaymentMethodsResolverInterface {
            /** @param list<PaymentMethodInterface> $methods */
            public function __construct(private readonly array $methods)
            {
            }

            public function getSupportedMethods(PaymentInterface $subject): array
            {
                return $this->methods;
            }

            public function supports(PaymentInterface $subject): bool
            {
                return true;
            }
        };
    }
}
