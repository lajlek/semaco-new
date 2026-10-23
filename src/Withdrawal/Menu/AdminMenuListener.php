<?php

declare(strict_types=1);

namespace App\Withdrawal\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $sales = $menu->getChild('sales') ?? $menu;

        $sales
            ->addChild('app_admin_withdrawal_request', [
                'route' => 'app_admin_withdrawal_request_index',
                'extras' => ['routes' => [['route' => 'app_admin_withdrawal_request_show']]],
            ])
            ->setLabel('Odstoupení od smlouvy')
            ->setLabelAttribute('icon', 'tabler:arrow-back-up');
    }
}
