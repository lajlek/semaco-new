<?php

declare(strict_types=1);

namespace App\Erp\Application\Order;

use App\Entity\Order\Order;
use App\Erp\Port\Order\OrderSyncInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderPushService
{
    public const STATE_EXPORTED = 'exported';

    public const STATE_ERROR = 'error';

    public function __construct(
        private OrderSyncInterface $orderSync,
        private OrderPushRequestBuilder $requestBuilder,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.erp.order_export_since%')]
        private string $exportSince,
    ) {
    }

    /** @return list<Order> */
    public function pendingOrders(int $limit): array
    {
        /** @var list<Order> $orders */
        $orders = $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.checkoutState = :completed')
            ->andWhere('o.checkoutCompletedAt >= :since')
            ->andWhere('o.erpExportState IS NULL OR o.erpExportState = :error')
            ->setParameter('completed', 'completed')
            ->setParameter('since', new \DateTimeImmutable($this->exportSince))
            ->setParameter('error', self::STATE_ERROR)
            ->orderBy('o.checkoutCompletedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $orders;
    }

    public function push(Order $order): string
    {
        try {
            $erpIdentifier = $this->orderSync->push($this->requestBuilder->build($order));
        } catch (\Throwable $exception) {
            $order->setErpExportState(self::STATE_ERROR);
            $this->entityManager->flush();

            throw $exception;
        }

        $order->setExternalIdentifier($erpIdentifier);
        $order->setErpExportState(self::STATE_EXPORTED);
        $this->entityManager->flush();

        return $erpIdentifier;
    }
}
