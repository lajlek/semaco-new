<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Partner\Fetch\RobotsPolicy;
use PHPUnit\Framework\TestCase;

final class RobotsPolicyTest extends TestCase
{
    public function testDisallowedPrefixBlocksWholeSection(): void
    {
        $policy = RobotsPolicy::parse("User-agent: *\nDisallow: /portal/\n");

        self::assertFalse($policy->isAllowed('https://www.pokolm.com/portal/pp25/products.xhtml?pt=1'));
        self::assertTrue($policy->isAllowed('https://www.pokolm.com/en/products'));
    }

    public function testWildcardsAndCrawlDelayForGenericAgent(): void
    {
        $policy = RobotsPolicy::parse("User-agent: Baiduspider\nDisallow: /\nUser-agent: *\nCrawl-delay: 10\nDisallow: /*pSid=\nDisallow: /Products/Articles/\n");

        self::assertSame(10.0, $policy->crawlDelay);
        self::assertTrue($policy->isAllowed('https://www.brw.ch/de-CH/futuro-vhm-radiuskopierfraeser-222401'));
        self::assertFalse($policy->isAllowed('https://www.brw.ch/de-CH/x?pSid=abc'));
        self::assertFalse($policy->isAllowed('https://www.brw.ch/Products/Articles/1'));
    }

    public function testSpecificGroupsDoNotApplyToGenericAgent(): void
    {
        $policy = RobotsPolicy::parse("User-agent: bingbot\nDisallow: /\n\nUser-agent: *\nDisallow: /search\n");

        self::assertTrue($policy->isAllowed('https://webshop.fraisa.com/CH/en/endmilling-tools'));
        self::assertFalse($policy->isAllowed('https://webshop.fraisa.com/search?q=1'));
    }

    public function testLongerAllowWinsOverDisallow(): void
    {
        $policy = RobotsPolicy::parse("User-agent: *\nDisallow: /shop/\nAllow: /shop/catalog/\n");

        self::assertTrue($policy->isAllowed('https://example.com/shop/catalog/a'));
        self::assertFalse($policy->isAllowed('https://example.com/shop/basket'));
    }
}
