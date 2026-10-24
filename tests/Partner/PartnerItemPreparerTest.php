<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Entity\Taxonomy\Taxon;
use App\Partner\Entity\PartnerItem;
use App\Partner\Rules\PartnerRules;
use App\Partner\Service\PartnerItemPreparer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class PartnerItemPreparerTest extends TestCase
{
    public function testNewFamilyGetsProposalFromSimilarFamilyAndUniqueNames(): void
    {
        $known = $this->item('P5337180', 'Cylindrical/Square end mills', ['d1' => '3', 'd2' => '6', 'l1' => '38', 'l2' => '4', 'z' => '4']);
        $known->match(PartnerItem::MATCH_MATCHED, $this->variant('Válcová fréza', 'valcove'));

        $first = $this->item('A8770178', 'Cylindrical/Square end mills E-Cut Compact', ['d1' => '3', 'd2' => '4', 'l1' => '30', 'l2' => '4.5', 'z' => '3']);
        $second = $this->item('A8770179', 'Cylindrical/Square end mills E-Cut Compact', ['d1' => '3', 'd2' => '6', 'l1' => '34', 'l2' => '4.5', 'z' => '3']);
        $third = $this->item('A8770180', 'Cylindrical/Square end mills E-Cut Compact', ['d1' => '3', 'd2' => '6', 'l1' => '34', 'l2' => '4.5', 'z' => '4']);

        $this->preparer([$known, $first, $second, $third])->prepare('fraisa');

        self::assertSame(PartnerItem::GROUPING_NEW, $first->getGrouping());
        self::assertSame('A8770', $first->getFamilyKey());
        self::assertSame('Válcová fréza', $first->getPreparedProductName());
        self::assertSame('valcove', $first->getPreparedTaxonCode());
        self::assertSame(['Průměr 3 D2=4 Z=3', 'Průměr 3 D2=6 Z=3', 'Průměr 3 D2=6 Z=4'], [$first->getPreparedName(), $second->getPreparedName(), $third->getPreparedName()]);
        self::assertSame('4,5', $first->getPreparedOptions()['option_group_3']['value']);
        self::assertTrue($first->canBeApproved());
        self::assertSame(PartnerItem::STATE_PAIRED, $known->getWorkflowState());
    }

    public function testNewFamilyWithoutSimilarFamilyNeedsReview(): void
    {
        $item = $this->item('X1000100', 'Thread mill', ['d1' => '10', 'd2' => '10', 'l1' => '70', 'l2' => '20', 'z' => '4']);

        $this->preparer([$item])->prepare('fraisa');

        self::assertSame(PartnerItem::STATE_NEEDS_REVIEW, $item->getWorkflowState());
        self::assertFalse($item->canBeApproved());
        self::assertContains('Chybí český název nového hlavního produktu', array_column($item->getIssues(), 'message'));
    }

    /** @param array<string, string> $parameters */
    private function item(string $code, string $name, array $parameters): PartnerItem
    {
        $item = new PartnerItem('fraisa', $code);
        $item->updateContent(null, $name, null, 'https://webshop.fraisa.com/x', null, $parameters, [], null, null, null, sha1($code));

        return $item;
    }

    private function variant(string $productName, string $taxonCode): ProductVariant
    {
        $taxon = new Taxon();
        $taxon->setCode($taxonCode);
        $product = new Product();
        $product->setCurrentLocale('cs_CZ');
        $product->setFallbackLocale('cs_CZ');
        $product->setName($productName);
        $product->setMainTaxon($taxon);
        $variant = new ProductVariant();
        $product->addVariant($variant);

        return $variant;
    }

    /** @param list<PartnerItem> $items */
    private function preparer(array $items): PartnerItemPreparer
    {
        $itemRepository = $this->createStub(RepositoryInterface::class);
        $itemRepository->method('findBy')->willReturn($items);
        $optionRepository = $this->createStub(RepositoryInterface::class);
        $optionRepository->method('findAll')->willReturn([]);

        return new PartnerItemPreparer($itemRepository, $optionRepository, new PartnerRules(), $this->createStub(EntityManagerInterface::class), 'cs_CZ');
    }
}
