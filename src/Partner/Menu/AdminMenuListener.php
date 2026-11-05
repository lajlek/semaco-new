<?php

declare(strict_types=1);

namespace App\Partner\Menu;

use App\Partner\Entity\PartnerItem;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $catalog = $menu->getChild('catalog') ?? $menu;

        $catalog
            ->addChild('app_admin_partner_item', [
                'route' => 'app_admin_partner_item_index',
                'routeParameters' => ['criteria' => ['workflowState' => PartnerItem::STATE_NEW]],
                'extras' => ['routes' => [
                    ['route' => 'app_admin_partner_item_index'],
                    ['route' => 'app_admin_partner_item_show'],
                    ['route' => 'app_admin_partner_export_batch_index'],
                ]],
            ])
            ->setLabel('Partnerský sortiment')
            ->setLabelAttribute('icon', 'tabler:building-warehouse');
    }
}
