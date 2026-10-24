<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Entity\Product\ProductVariant;
use App\Partner\Entity\PartnerExportBatch;
use App\Partner\Entity\PartnerItem;
use PHPUnit\Framework\TestCase;

final class PartnerItemWorkflowTest extends TestCase
{
    public function testItemWithoutBlockingIssuesCanBeApprovedAndExported(): void
    {
        $item = $this->item('a');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', [], [self::issue(false)]);

        self::assertSame(PartnerItem::STATE_NEW, $item->getWorkflowState());
        self::assertTrue($item->canBeApproved());

        $item->approve('admin');
        $item->markExported(new PartnerExportBatch('admin', 1, ''));
        self::assertSame(PartnerItem::STATE_EXPORTED, $item->getWorkflowState());

        $item->match(PartnerItem::MATCH_MATCHED, new ProductVariant());
        self::assertSame(PartnerItem::STATE_PAIRED, $item->getWorkflowState());
    }

    public function testBlockingIssueNeedsReview(): void
    {
        $item = $this->item('a');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, null, null, null, [], [self::issue(true)]);

        self::assertSame(PartnerItem::STATE_NEEDS_REVIEW, $item->getWorkflowState());
        self::assertFalse($item->canBeApproved());
    }

    public function testApprovalSurvivesUnchangedReloadButNotChangedData(): void
    {
        $item = $this->item('a');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', [], []);
        $item->approve('admin');

        $this->reload($item, 'a');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', [], []);
        self::assertSame(PartnerItem::STATE_READY, $item->getWorkflowState());

        $this->reload($item, 'b');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', [], []);
        self::assertSame(PartnerItem::STATE_NEEDS_REVIEW, $item->getWorkflowState());
        self::assertSame(PartnerItem::ISSUE_CHANGED, $item->getIssues()[0]['code']);
        self::assertTrue($item->canBeApproved());
        self::assertFalse($item->canBeApproved(false));
        self::assertFalse($item->isExportable());

        $item->approve('admin');
        self::assertSame([], $item->getIssues());
    }

    public function testApprovalIgnoresStoredKeyOrder(): void
    {
        $options = ['option_group_3' => ['value' => '4,5', 'source' => 'partner'], 'option_group_1' => ['value' => '3', 'source' => 'partner']];
        $item = $this->item('a');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', $options, []);
        $item->approve('admin');

        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', array_reverse($options, true), []);

        self::assertSame(PartnerItem::STATE_READY, $item->getWorkflowState());
        self::assertTrue($item->isExportable());
    }

    public function testReadyItemWithNewBlockingIssueIsNotExportable(): void
    {
        $item = $this->item('a');
        $item->applyPreparation('F', PartnerItem::GROUPING_NEW, null, 'Průměr 3', 'Fréza', 'valcove', [], []);
        $item->approve('admin');
        self::assertTrue($item->isExportable());

        $item->applyPreparation('F', PartnerItem::GROUPING_AMBIGUOUS, null, 'Průměr 3', null, null, [], [self::issue(true)]);

        self::assertSame(PartnerItem::STATE_NEEDS_REVIEW, $item->getWorkflowState());
        self::assertFalse($item->isExportable());
    }

    public function testExistingItemIsPaired(): void
    {
        $item = $this->item('a');
        $item->match(PartnerItem::MATCH_MATCHED, new ProductVariant());
        $item->applyPreparation('F', PartnerItem::GROUPING_EXISTING, null, 'Průměr 3', null, null, [], []);

        self::assertSame(PartnerItem::STATE_PAIRED, $item->getWorkflowState());
        self::assertFalse($item->canBeApproved());
    }

    private function item(string $hash): PartnerItem
    {
        $item = new PartnerItem('fraisa', 'P1');
        $this->reload($item, $hash);

        return $item;
    }

    private function reload(PartnerItem $item, string $hash): void
    {
        $item->updateContent(null, 'Fréza', null, 'https://example.test', null, [], [], null, null, null, $hash);
    }

    /** @return array{code: string, message: string, blocking: bool} */
    private static function issue(bool $blocking): array
    {
        return ['code' => 'x', 'message' => 'x', 'blocking' => $blocking];
    }
}
