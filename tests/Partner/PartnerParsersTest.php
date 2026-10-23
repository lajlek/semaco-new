<?php

declare(strict_types=1);

namespace App\Tests\Partner;

use App\Partner\Parser\BrwFamilyParser;
use App\Partner\Parser\FraisaListingParser;
use App\Partner\Parser\PokolmListingParser;
use PHPUnit\Framework\TestCase;

final class PartnerParsersTest extends TestCase
{
    public function testFraisaRowsAndPaging(): void
    {
        $html = <<<'HTML'
            <div data-testing-sku="P5337180"><a class="product-title" href="/CH/en/x-prdP5337180-ctg1?view=list">Cylindrical/Square end mills <span>Smooth-edged, short-shank version</span></a>
            <div class="product-description">ø3/6x38/4 z4</div><img class="product-image" src="https://cdn.example/4946.png"></div>
            <div data-testing-sku="P5337180"></div>
            <ul class="product-list-paging"><li><a href="/CH/en/x-ctg1?view=list&page=2#family-page-top">2</a></li></ul>
            HTML;

        $page = (new FraisaListingParser())->parse($html, 'https://webshop.fraisa.com/CH/en/x-ctg1?view=list');

        self::assertCount(1, $page->items);
        $item = $page->items[0];
        self::assertSame('P5337180', $item->orderCode);
        self::assertSame('Cylindrical/Square end mills', $item->name);
        self::assertSame('Smooth-edged, short-shank version', $item->subtitle);
        self::assertSame(['spec' => 'ø3/6x38/4 z4', 'd1' => '3', 'd2' => '6', 'l1' => '38', 'l2' => '4', 'z' => '4'], $item->parameters);
        self::assertSame('https://webshop.fraisa.com/CH/en/x-prdP5337180-ctg1', $item->detailUrl);
        self::assertSame(['https://webshop.fraisa.com/CH/en/x-ctg1?view=list&page=2'], $page->pageUrls);
    }

    public function testFraisaSpecWithRadius(): void
    {
        self::assertSame(['d1' => '3', 'd2' => '6', 'l1' => '57', 'l2' => '8', 'r' => '0.2', 'z' => '4'], FraisaListingParser::parseSpec('ø3/6x57/8 r0.2 z4'));
    }

    public function testBrwFamilyRows(): void
    {
        $html = <<<'HTML'
            <div class="brw-product-detail-top-box-description"><p>222401</p><h1>FUTURO VHM-Radiuskopierfräser</h1></div>
            <div class="brw-product-detail-page-gallery"><a data-src="https://static.brw.ch/images800x800/F222401.2270.jpg"></a></div>
            <div class="brw_product_page_article_list_footer_info"> 241 gefundene Artikel </div>
            <div class="brw_product_page_article_list_footer_pagination_box"><a href="https://www.brw.ch/de-CH/product/393789?page=2#top">2</a></div>
            <div id="product-variants"><div class="brw_product_page_article_list_row">
              <div><div class="brw_product_page_label_container"> 222401.0010 </div><a class="product_link_show_tab">VHM-Kugelfräser FUTURO</a><br> Ø0.1 x r0.05 x 0.3</div>
              <div class="brw_article_attributes"><div><div class="d-sm-none">d1 [mm]</div><div>0.1</div></div><div><div class="d-sm-none">r [mm]</div><div>0.05</div></div></div>
            </div></div>
            HTML;

        $page = (new BrwFamilyParser())->parse($html, 'https://www.brw.ch/de-CH/futuro-vhm-radiuskopierfraeser-222401');

        self::assertSame(241, $page->totalCount);
        self::assertSame(['https://www.brw.ch/de-CH/product/393789?page=2'], $page->pageUrls);
        self::assertCount(1, $page->items);
        self::assertSame('222401.0010', $page->items[0]->orderCode);
        self::assertSame('222401', $page->items[0]->familyCode);
        self::assertSame('0.05', $page->items[0]->parameters['r [mm]']);
        self::assertSame('Ø0.1 x r0.05 x 0.3', $page->items[0]->parameters['spec']);
    }

    public function testPokolmTableUsesColumnHeaders(): void
    {
        $html = <<<'HTML'
            <table id="productsForm:productsTable"><thead><tr><th><span class="ui-column-title"></span></th><th><span class="ui-column-title">Art.-Nr.</span></th><th><span class="ui-column-title">d1</span></th><th><span class="ui-column-title">z</span></th></tr></thead>
            <tbody id="productsForm:productsTable_data"><tr data-rk="4 40 331"><td><img src="x.png"></td><td><a class="pp-product-artnr" href="https://www.pokolm.com/portal/pp25/details.xhtml?artnr=4+40+331">4 40 331</a><nobr class="pp-text__gray">shell-type face milling cutter body Planworx®</nobr></td><td>40</td><td>4</td></tr></tbody></table>
            HTML;

        $page = (new PokolmListingParser())->parse($html, 'https://www.pokolm.com/portal/pp25/products.xhtml');

        self::assertFalse((new PokolmListingParser())->supportsAutomaticFetching());
        self::assertCount(1, $page->items);
        self::assertSame('4 40 331', $page->items[0]->orderCode);
        self::assertSame(['d1' => '40', 'z' => '4'], $page->items[0]->parameters);
    }
}
