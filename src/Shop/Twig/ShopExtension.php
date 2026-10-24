<?php

declare(strict_types=1);

namespace App\Shop\Twig;

use App\Shop\Service\OrientationalPriceConverter;
use App\Shop\Service\VariantOrderCodeResolverInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ShopExtension extends AbstractExtension
{
    public function __construct(
        private readonly OrientationalPriceConverter $orientationalPriceConverter,
        private readonly VariantOrderCodeResolverInterface $variantOrderCodeResolver,
        /** @var TaxonRepositoryInterface<TaxonInterface> */
        #[Autowire(service: 'sylius.repository.taxon')]
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('semaco_orientational_price', $this->orientationalPriceConverter->convert(...)),
            new TwigFunction('semaco_order_code', $this->orderCode(...)),
            new TwigFunction('semaco_taxon_by_slug', $this->taxonBySlug(...)),
        ];
    }

    private function orderCode(?ProductVariantInterface $variant): string
    {
        return null === $variant ? '' : $this->variantOrderCodeResolver->resolve($variant);
    }

    private function taxonBySlug(?string $slug): ?TaxonInterface
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $taxon = $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());

        return $taxon instanceof TaxonInterface ? $taxon : null;
    }
}
