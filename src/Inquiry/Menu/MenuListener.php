<?php

declare(strict_types=1);

namespace App\Inquiry\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class MenuListener
{
    #[AsEventListener(event: 'sylius.menu.admin.main')]
    public function onAdminMenu(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $customers = $menu->getChild('customers') ?? $menu;

        $customers
            ->addChild('app_admin_registration_request', [
                'route' => 'app_admin_registration_request_index',
                'extras' => ['routes' => [['route' => 'app_admin_registration_request_show']]],
            ])
            ->setLabel('Žádosti o registraci')
            ->setLabelAttribute('icon', 'tabler:user-plus');

        $customers
            ->addChild('app_admin_contact_message', [
                'route' => 'app_admin_contact_message_index',
                'extras' => ['routes' => [['route' => 'app_admin_contact_message_show']]],
            ])
            ->setLabel('Kontaktní formulář')
            ->setLabelAttribute('icon', 'tabler:messages');
    }

    #[AsEventListener(event: 'sylius.menu.shop.account')]
    public function onAccountMenu(MenuBuilderEvent $event): void
    {
        $event->getMenu()
            ->addChild('contact_messages', ['route' => 'app_shop_account_contact_message_index'])
            ->setLabel('Moje zprávy')
            ->setLabelAttribute('icon', 'tabler:messages');
    }
}
