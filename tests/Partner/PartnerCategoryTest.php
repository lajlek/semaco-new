<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Partner\Entity\PartnerCategory;
use App\Partner\Rules\PartnerRules;
use PHPUnit\Framework\TestCase;

final class PartnerCategoryTest extends TestCase
{
    public function testKeywordsMustAllBeInTheLastUrlSegment(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO frézy', null, 'futuro fraeser');

        self::assertTrue($category->matchesUrl('https://www.brw.ch/de-CH/futuro-vhm-radiuskopierfraeser-222401'));
        self::assertTrue($category->matchesUrl('https://www.brw.ch/de-CH/FUTURO-Fraeser-222401'));
        self::assertFalse($category->matchesUrl('https://www.brw.ch/de-CH/messschieber-digital-futuro-109054'));
        self::assertFalse($category->matchesUrl('https://www.brw.ch/futuro/fraeser-tesa-109051'));
    }

    public function testCategoryWithoutKeywordsMatchesNothing(): void
    {
        self::assertFalse((new PartnerCategory('fraisa', 'Frézy', 'https://webshop.fraisa.com/x', null))->matchesUrl('https://webshop.fraisa.com/x'));
    }

    public function testFinishedCategoryCanBeRequestedAgainOnlyAfterCooldown(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO', null, 'futuro');
        self::assertSame(PartnerCategory::STATE_IDLE, $category->getState());
        self::assertTrue($category->requestFetch(new \DateTimeImmutable('-6 hours')));
        self::assertTrue($category->isActive());
        self::assertSame(PartnerCategory::STATE_RUNNING, $category->getState());

        $category->begin([]);
        $category->finish();
        self::assertSame(PartnerCategory::STATE_DONE, $category->getState());
        self::assertFalse($category->isActive());
        self::assertFalse($category->requestFetch(new \DateTimeImmutable('-6 hours')));
        self::assertTrue($category->requestFetch(new \DateTimeImmutable('+1 hour')));
    }

    public function testUncheckedCategoryIsPausedAndKeepsItsProgress(): void
    {
        $category = new PartnerCategory('brw', 'FUTURO', null, 'futuro');
        $category->requestFetch(new \DateTimeImmutable('-6 hours'));
        $category->begin(['https://www.brw.ch/a', 'https://www.brw.ch/b']);
        $category->markVisited('https://www.brw.ch/a', ['https://www.brw.ch/a', 'https://www.brw.ch/b', 'https://www.brw.ch/c']);

        $category->setEnabled(false);

        self::assertFalse($category->isActive());
        self::assertSame(PartnerCategory::STATE_PAUSED, $category->getState());
        self::assertSame(['https://www.brw.ch/b', 'https://www.brw.ch/c'], $category->getPendingUrls());
        self::assertSame(1, $category->getPagesFetched());
    }

    public function testBrwTakesOnlyItsOwnBrandsAndProductUrls(): void
    {
        $rule = (new PartnerRules())->get('brw');

        self::assertTrue($rule->allowsTitle('FUTURO VHM-Radiuskopierfräser'));
        self::assertTrue($rule->allowsTitle('Messschieber NERIOX'));
        self::assertFalse($rule->allowsTitle('Messschieber digital TESA TWIN-CAL'));
        self::assertFalse($rule->allowsTitle('FUTUROLOGIE'));
        self::assertFalse($rule->allowsTitle(null));
        self::assertTrue($rule->isListingUrl('https://www.brw.ch/de-CH/futuro-vhm-radiuskopierfraeser-222401'));
        self::assertFalse($rule->isListingUrl('https://www.brw.ch/de-CH/futuro-werkzeuge-ts_220433'));
    }

    public function testBrwTakesOnlyPagesFromAllowedCategories(): void
    {
        $rule = (new PartnerRules())->get('brw');

        self::assertTrue($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'Fertigungstechnik', 'Fräsen', 'Fräsen VHM']));
        self::assertTrue($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'Maschinenwerkzeuge', 'Bohren Metal']));
        self::assertTrue($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'FUTURO Werkzeuge', 'FUTURO Senken, Reiben, Drehen, Sägen und Trennen']));
        self::assertTrue($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'NERIOX Werkzeuge', 'NERIOX Fertigungstechnik', 'NERIOX Fräsen HSS']));
        self::assertFalse($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'FUTURO Werkzeuge', 'FUTURO Messtechnik']));
        self::assertFalse($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'FUTURO Werkzeuge', 'FUTURO Spanntechnik']));
        self::assertFalse($rule->allowsCategoryPath(['Startseite', 'BRW ToolShop', 'Messwerkzeuge', 'Signieren und Markieren']));
        self::assertTrue($rule->allowsCategoryPath([]));
    }

    public function testOtherPartnersHaveNoBrandLimit(): void
    {
        self::assertTrue((new PartnerRules())->get('fraisa')->allowsTitle(null));
        self::assertTrue((new PartnerRules())->get('fraisa')->allowsCategoryPath(['Messtechnik']));
    }
}
