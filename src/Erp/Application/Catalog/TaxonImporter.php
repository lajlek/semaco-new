<?php

declare(strict_types=1);

namespace App\Erp\Application\Catalog;

use App\Entity\Taxonomy\Taxon;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Catalog\Dto\TaxonSnapshot;
use App\Erp\Port\Catalog\TaxonSyncInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class TaxonImporter
{
    public const ROOT_CODE = 'main_taxon_1';

    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     * @param FactoryInterface<TaxonInterface> $taxonFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private TaxonSyncInterface $taxonSync,
        private TaxonRepositoryInterface $taxonRepository,
        private FactoryInterface $taxonFactory,
        private ChannelRepositoryInterface $channelRepository,
        private EntityManagerInterface $entityManager,
        private string $channelCode,
        private string $localeCode,
    ) {
    }

    public function import(): ImportResult
    {
        $result = new ImportResult('taxons');
        $root = $this->findOrCreateRoot();

        foreach ($this->taxonSync->pullAll() as $snapshot) {
            $taxon = $this->taxonRepository->findOneBy(['externalIdentifier' => $snapshot->externalIdentifier]);
            $isNew = !$taxon instanceof Taxon;
            if ($isNew) {
                $taxon = $this->createTaxon($snapshot);
                $result->created();
            } else {
                $result->updated();
            }

            $this->apply($taxon, $snapshot, $this->resolveParent($snapshot, $root));
            $this->entityManager->flush();
        }

        return $result;
    }

    private function createTaxon(TaxonSnapshot $snapshot): Taxon
    {
        $code = CatalogCodeGenerator::taxonCode($snapshot->name);
        if ($this->taxonRepository->findOneBy(['code' => $code]) !== null) {
            $code .= '_' . $snapshot->externalIdentifier;
        }

        $taxon = $this->taxonFactory->createNew();
        \assert($taxon instanceof Taxon);
        $taxon->setCode($code);
        $taxon->setExternalIdentifier($snapshot->externalIdentifier);
        $taxon->setCurrentLocale($this->localeCode);
        $taxon->setFallbackLocale($this->localeCode);
        $taxon->setSlug(CatalogCodeGenerator::slugFromCode($code));
        $this->entityManager->persist($taxon);

        return $taxon;
    }

    private function apply(Taxon $taxon, TaxonSnapshot $snapshot, TaxonInterface $parent): void
    {
        $taxon->setCurrentLocale($this->localeCode);
        $taxon->setFallbackLocale($this->localeCode);
        $taxon->setParent($parent);
        $taxon->setPosition($snapshot->position);
        $taxon->setEnabled($snapshot->enabled);
        $taxon->setName($snapshot->name);
        $taxon->setDescription($snapshot->description);
    }

    private function resolveParent(TaxonSnapshot $snapshot, TaxonInterface $root): TaxonInterface
    {
        if ($snapshot->parentExternalIdentifier === null) {
            return $root;
        }

        $parent = $this->taxonRepository->findOneBy(['externalIdentifier' => $snapshot->parentExternalIdentifier]);

        return $parent instanceof TaxonInterface ? $parent : $root;
    }

    private function findOrCreateRoot(): TaxonInterface
    {
        $channel = $this->channelRepository->findOneByCode($this->channelCode);
        $root = $this->taxonRepository->findOneBy(['code' => self::ROOT_CODE]);
        $root ??= $channel instanceof ChannelInterface ? $channel->getMenuTaxon() : null;

        if (!$root instanceof TaxonInterface) {
            $root = $this->taxonFactory->createNew();
            $root->setCode(self::ROOT_CODE);
            $root->setCurrentLocale($this->localeCode);
            $root->setFallbackLocale($this->localeCode);
            $root->setName('E-shop');
            $root->setSlug('e-shop');
            $this->entityManager->persist($root);
        }

        if ($channel instanceof ChannelInterface && $channel->getMenuTaxon() === null) {
            $channel->setMenuTaxon($root);
        }

        $this->entityManager->flush();

        return $root;
    }
}
