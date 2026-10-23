<?php

declare(strict_types=1);

namespace App\Cms\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $content = $menu->getChild('marketing') ?? $menu;

        $content
            ->addChild('app_admin_cms_page', ['route' => 'app_admin_cms_page_index'])
            ->setLabel('Stránky (CMS)')
            ->setLabelAttribute('icon', 'tabler:file-text');
    }
}
