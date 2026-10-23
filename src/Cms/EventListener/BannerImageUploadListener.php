<?php

declare(strict_types=1);

namespace App\Cms\EventListener;

use App\Cms\Entity\Banner;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsEventListener(event: 'app.cms_banner.pre_create')]
#[AsEventListener(event: 'app.cms_banner.pre_update')]
final readonly class BannerImageUploadListener
{
    public function __construct(private ImageUploaderInterface $imageUploader)
    {
    }

    public function __invoke(GenericEvent $event): void
    {
        $banner = $event->getSubject();
        if (!$banner instanceof Banner || !$banner->hasFile()) {
            return;
        }

        $this->imageUploader->upload($banner);
        $banner->setFile(null);
        $banner->touch();
    }
}
