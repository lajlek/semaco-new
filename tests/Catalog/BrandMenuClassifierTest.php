<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\BrandMenu\BrandMenuClassifier;
use App\Catalog\BrandMenu\BrandMenuTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrandMenuClassifierTest extends TestCase
{
    /** @param list<string> $taxons */
    #[DataProvider('products')]
    public function testProductGoesToTheMakersCategory(?string $brand, ?string $group, array $taxons, string $name, string $expected): void
    {
        self::assertSame([$expected], BrandMenuClassifier::classify($brand, $group, $taxons, $name));
    }

    /** @return iterable<string, array{string|null, string|null, list<string>, string, string}> */
    public static function products(): iterable
    {
        yield 'POKOLM rovinná' => ['POKOLM', '180', ['frezovani_s_vbd', 'rovinne'], 'PLANWORX® D 50 mm', 'pokolm_frezy_rovinne'];
        yield 'POKOLM kulaté destičky' => ['POKOLM', '180', ['frezovani_s_vbd', 'toroidni_45'], 'Fréza s kulatou destičkou r 5 D 25 mm', 'pokolm_frezy_kopirovaci'];
        yield 'POKOLM Uniworx Plus' => ['POKOLM', '180', ['toroidni_45'], 'UNIWORX® PLUS r 2 | r 4 D 16 mm', 'pokolm_frezy_posuv'];
        yield 'POKOLM Uniworx' => ['POKOLM', '180', ['toroidni_45'], 'UNIWORX® D 16 mm', 'pokolm_frezy_kulove'];
        yield 'POKOLM Coolcap před HSK' => ['POKOLM', '180', ['upinani', 'hsk'], 'COOLCAP® D 6 | form A', 'pokolm_upinani_coolcap'];
        yield 'POKOLM SK' => ['POKOLM', '180', ['upinani', 'iso'], 'Upínač D 6 | DIN 69871 AD', 'pokolm_upinani_sk'];
        yield 'bez značky podle skupiny POKOLM' => [null, '180', ['upinani', 'iso'], 'Středící kužel D 16 | DIN 2080 AD', 'pokolm_upinani_sk'];
        yield 'FRAISA AX' => ['FRAISA', '120', ['frezovani', 'valcove'], 'Válcová fréza AX-FPS', 'fraisa_stopkove_ax'];
        yield 'FRAISA MicrotoroXG před MicroX' => ['FRAISA', '120', ['valcove'], 'Rohová fréza MicroX (MicrotoroXG)', 'fraisa_mikro_xg'];
        yield 'FRAISA závitová fréza ve válcových' => ['FRAISA', '120', ['valcove'], 'Vícenásobná závitová fréza', 'fraisa_zavity_frezy'];
        yield 'FRAISA Microdrill zůstane u vrtání' => ['FRAISA', '120', ['vrtani'], 'Mikrovrták Microdrill NX', 'fraisa_vrtani_dalsi'];
        yield 'FRAISA bez řady' => ['FRAISA', '120', ['valcove'], 'Válcová fréza', 'fraisa_stopkove_dalsi'];
        yield 'bez značky podle skupiny FRAISA' => [null, '120', ['rezne'], 'Závitník Xtap', 'fraisa_zavity_xtap'];
        yield 'bez značky podle skupiny BRW' => [null, '117', ['vystruzovani'], 'Strojní výstružník D 1,51 - 2,0', 'brw_vystruzovani'];
        yield 'jiný výrobce' => ['Premio', '299', ['vrtani'], 'Vrták', 'ostatni_dalsi'];
        yield 'jiná značka nepřebije skupina' => ['Premio', '120', ['vrtani'], 'Vrták', 'ostatni_dalsi'];
        yield 'bez značky a neznámá skupina' => [null, '999', [], 'Něco', 'ostatni_dalsi'];
    }

    public function testPriceHitStaysInItsOwnCategoryNextToTheMaker(): void
    {
        self::assertSame(['brw_frezovani', 'ostatni_hity'], BrandMenuClassifier::classify('BRW', '117', ['frezovani', 'kulove', 'cenove_hity', 'frezy'], 'Kulová fréza FUTURO'));
        self::assertSame(['ostatni_hity'], BrandMenuClassifier::classify('Premio', '299', ['cenove_hity', 'vrtaky'], 'Vrták'));
    }

    public function testEveryRuleTargetExistsInTheTree(): void
    {
        foreach (BrandMenuTree::nodes() as $key => $node) {
            self::assertSame(1, preg_match('#^[a-z0-9/-]+$#', BrandMenuTree::slug($key)));
            if ($node['parent'] !== null) {
                self::assertArrayHasKey($node['parent'], BrandMenuTree::nodes());
            }
        }
        self::assertSame(['fraisa_stopkove_ax', 'fraisa_stopkove', 'fraisa'], BrandMenuTree::withAncestors('fraisa_stopkove_ax'));
        self::assertSame('fraisa/stopkove-frezy/ax', BrandMenuTree::slug('fraisa_stopkove_ax'));
    }

    public function testMenuTaxonsAreRecognizedSoSynchronizationKeepsThem(): void
    {
        self::assertTrue(BrandMenuTree::isMenuTaxon('vyrobci'));
        self::assertTrue(BrandMenuTree::isMenuTaxon(BrandMenuTree::code('fraisa')));
        self::assertFalse(BrandMenuTree::isMenuTaxon('frezovani'));
        self::assertFalse(BrandMenuTree::isMenuTaxon(null));
    }
}
