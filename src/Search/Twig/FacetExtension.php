<?php

declare(strict_types=1);

namespace App\Search\Twig;

use App\Search\Service\AttributeFacetProvider;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

final readonly class FacetExtension
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private AttributeFacetProvider $facetProvider,
        private LocaleContextInterface $localeContext,
        #[Autowire(service: 'sylius.repository.taxon')]
        private TaxonRepositoryInterface $taxonRepository,
    ) {
    }

    /** @return list<array{code: string, name: string, filterType: int|null, values: array<string, string>}> */
    #[AsTwigFunction('semaco_taxon_facets')]
    public function facets(?string $taxonSlug): array
    {
        if ($taxonSlug === null || $taxonSlug === '') {
            return [];
        }

        $locale = $this->localeContext->getLocaleCode();
        $taxon = $this->taxonRepository->findOneBySlug($taxonSlug, $locale);

        return $taxon instanceof TaxonInterface ? $this->facetProvider->forTaxon($taxon, $locale) : [];
    }
}
