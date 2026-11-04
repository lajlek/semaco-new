<?php

declare(strict_types=1);

namespace App\Partner\Service;

use App\Partner\Entity\PartnerCategory;
use App\Partner\Rules\PartnerRules;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PartnerCategories
{
    /** @param RepositoryInterface<PartnerCategory> $categoryRepository */
    public function __construct(
        #[Autowire(service: 'app.repository.partner_category')]
        private RepositoryInterface $categoryRepository,
        private EntityManagerInterface $entityManager,
        private PartnerRules $rules,
        private PartnerParserRegistry $parsers,
    ) {
    }

    /** @return array<string, list<PartnerCategory>> partner => categories */
    public function byPartner(): array
    {
        $grouped = [];
        foreach ($this->categoryRepository->findBy([], ['partnerCode' => 'ASC', 'name' => 'ASC']) as $category) {
            $grouped[$category->getPartnerCode()][] = $category;
        }

        return $grouped;
    }

    public function add(string $partnerCode, string $name, string $startUrl, string $urlKeywords): PartnerCategory
    {
        $rule = $this->rules->get($partnerCode);
        if (!$this->parsers->get($partnerCode)->supportsAutomaticFetching()) {
            throw new \DomainException(sprintf('%s se nenačítá automaticky, kategorie se u něj nezadávají. Uložené stránky nahrajte ručně.', $rule->label));
        }
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \DomainException('Vyplňte název kategorie (nejvýš 255 znaků).');
        }
        if ($startUrl !== '' && !$rule->isAllowedUrl($startUrl)) {
            throw new \DomainException(sprintf('Adresa musí začínat https://%s/', implode(' nebo https://', $rule->allowedHosts)));
        }
        if ($urlKeywords !== '' && ($rule->sitemapUrl === null || mb_strlen($urlKeywords) > 255)) {
            throw new \DomainException(sprintf('U partnera %s se kategorie zadává adresou výpisu, ne slovy.', $rule->label));
        }
        if ($startUrl === '' && $urlKeywords === '') {
            throw new \DomainException($rule->sitemapUrl !== null
                ? 'Vyplňte slova z adresy produktu (např. futuro), nebo adresu výpisu.'
                : 'Vyplňte adresu výpisu kategorie.');
        }

        $category = new PartnerCategory($partnerCode, $name, $startUrl !== '' ? $startUrl : null, $urlKeywords !== '' ? $urlKeywords : null);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /** @param list<int> $enabledIds */
    public function select(array $enabledIds): void
    {
        foreach ($this->categoryRepository->findAll() as $category) {
            $category->setEnabled(in_array($category->getId(), $enabledIds, true));
        }
        $this->entityManager->flush();
    }

    public function delete(int $id): void
    {
        $category = $this->categoryRepository->find($id);
        if (!$category instanceof PartnerCategory) {
            return;
        }
        if ($category->isEnabled()) {
            throw new \DomainException('Nejdřív kategorii odškrtněte a uložte výběr.');
        }
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
