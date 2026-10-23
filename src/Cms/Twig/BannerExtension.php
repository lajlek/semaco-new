<?php

declare(strict_types=1);

namespace App\Cms\Twig;

use App\Cms\Entity\Banner;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BannerExtension extends AbstractExtension
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('semaco_banner', $this->find(...))];
    }

    public function find(string $code): ?Banner
    {
        $banner = $this->entityManager->getRepository(Banner::class)->findOneBy(['code' => $code, 'enabled' => true]);

        return $banner instanceof Banner && $banner->getPath() !== null ? $banner : null;
    }
}
