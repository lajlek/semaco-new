<?php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon')]
#[ORM\Index(name: 'IDX_taxon_external_id', columns: ['external_identifier'])]
class Taxon extends BaseTaxon
{
    #[ORM\Column(name: 'external_identifier', type: 'string', length: 128, nullable: true)]
    private ?string $externalIdentifier = null;

    #[ORM\Column(name: 'show_in_menu', type: 'boolean', options: ['default' => true])]
    private bool $showInMenu = true;

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): void
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    public function isShowInMenu(): bool
    {
        return $this->showInMenu;
    }

    public function setShowInMenu(bool $showInMenu): void
    {
        $this->showInMenu = $showInMenu;
    }

    protected function createTranslation(): TaxonTranslationInterface
    {
        return new TaxonTranslation();
    }
}
