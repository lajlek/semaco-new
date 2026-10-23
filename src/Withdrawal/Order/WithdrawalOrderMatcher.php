<?php

declare(strict_types=1);

namespace App\Withdrawal\Order;

use App\Entity\Order\Order;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;

final readonly class WithdrawalOrderMatcher
{
    private const NUMBER_LENGTH = 9;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function match(?string $orderNumber, ?string $email): ?Order
    {
        $number = preg_replace('/\D+/', '', (string) $orderNumber) ?? '';
        if ($number === '' || $email === null || strlen($number) > self::NUMBER_LENGTH) {
            return null;
        }

        $order = $this->entityManager->getRepository(Order::class)->findOneBy([
            'number' => str_pad($number, self::NUMBER_LENGTH, '0', \STR_PAD_LEFT),
        ]);
        if (!$order instanceof Order || $order->getState() === OrderInterface::STATE_CART) {
            return null;
        }

        $customerEmail = $order->getCustomer()?->getEmail();

        return $customerEmail !== null && mb_strtolower($customerEmail) === mb_strtolower(trim($email)) ? $order : null;
    }
}
