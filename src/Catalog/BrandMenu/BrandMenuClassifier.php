<?php

declare(strict_types=1);

namespace App\Catalog\BrandMenu;

final class BrandMenuClassifier
{
    /** @var array<string, string> */
    private const MAKER_BY_BRAND = ['POKOLM' => BrandMenuTree::POKOLM, 'FRAISA' => BrandMenuTree::FRAISA, 'BRW' => BrandMenuTree::BRW];

    /** @var array<int, string> */
    private const MAKER_BY_GOODS_GROUP =['117' => BrandMenuTree::BRW, '120' => BrandMenuTree::FRAISA, '180' => BrandMenuTree::POKOLM];

    /** @var array<string, string> */
    private const POKOLM_BY_TAXON = [
        'vbd' => 'pokolm_desticky',
        'rovinne' => 'pokolm_frezy_rovinne',
        'rohove' => 'pokolm_frezy_rohove',
        'rychlostni' => 'pokolm_frezy_posuv',
        'kulove_57' => 'pokolm_frezy_kulove',
        'prislusenstvi' => 'pokolm_prislusenstvi',
        'hsk' => 'pokolm_upinani_hsk',
        'iso' => 'pokolm_upinani_sk',
        'bt' => 'pokolm_upinani_bt',
        'prodlouzeni' => 'pokolm_upinani_adaptery',
        'ostatni_59' => 'pokolm_upinani_adaptery',
    ];

    /** @var array<string, string> */
    private const FRAISA_MICRO_BY_NAME = [
        '/micro(?:sphero|toro)/iu' => 'fraisa_mikro_xg',
        '/microhx/iu' => 'fraisa_mikro_microhx',
        '/microx/iu' => 'fraisa_mikro_microx',
        '/microcut/iu' => 'fraisa_mikro_microcut',
    ];

    /** @var array<string, string> */
    private const FRAISA_END_MILL_BY_NAME = [
        '/xfeed/iu' => 'fraisa_stopkove_xfeed',
        '/spherox/iu' => 'fraisa_stopkove_spherox',
        '/sphericut/iu' => 'fraisa_stopkove_sphericut',
        '/arcut/iu' => 'fraisa_stopkove_arcut',
        '/\bAX\b/u' => 'fraisa_stopkove_ax',
        '/\bNX\b/u' => 'fraisa_stopkove_nx',
        '/\bSX\b/u' => 'fraisa_stopkove_sx',
        '/\bHX\b|xspeed/iu' => 'fraisa_stopkove_hx',
        '/e-cut/iu' => 'fraisa_stopkove_ecut',
        '/\bMFC\b/u' => 'fraisa_stopkove_mfc',
    ];

    /**
     * @param list<string> $taxonCodes
     *
     * @return list<string>
     */
    public static function classify(?string $brandName, ?string $goodsGroup, array $taxonCodes, string $name): array
    {
        $priceHit = in_array('cenove_hity', $taxonCodes, true);
        $leaf = match (self::maker($brandName, $goodsGroup)) {
            BrandMenuTree::POKOLM => self::pokolm($taxonCodes, $name),
            BrandMenuTree::FRAISA => self::fraisa($taxonCodes, $name),
            BrandMenuTree::BRW => self::brw($taxonCodes, $name),
            default => $priceHit ? null : 'ostatni_dalsi',
        };

        return array_values(array_filter([$leaf, $priceHit ? 'ostatni_hity' : null], static fn (?string $key): bool => $key !== null));
    }

    private static function maker(?string $brandName, ?string $goodsGroup): ?string
    {
        if ($brandName !== null && $brandName !== '') {
            return self::MAKER_BY_BRAND[mb_strtoupper(trim($brandName))] ?? null;
        }

        return self::MAKER_BY_GOODS_GROUP[trim((string) $goodsGroup)] ?? null;
    }

    /** @param list<string> $taxonCodes */
    private static function pokolm(array $taxonCodes, string $name): string
    {
        if (preg_match('/coolcap/iu', $name) === 1) {
            return 'pokolm_upinani_coolcap';
        }
        if (preg_match('/plananlage/iu', $name) === 1) {
            return 'pokolm_upinani_plananlage';
        }
        if (in_array('toroidni_45', $taxonCodes, true)) {
            return match (true) {
                preg_match('/spinworx|kulatou dest/iu', $name) === 1 => 'pokolm_frezy_kopirovaci',
                preg_match('/uniworx\W*plus|slotworx\W*hp|fourworx|trigaworx|doubletriga|quadworx/iu', $name) === 1 => 'pokolm_frezy_posuv',
                preg_match('/slotworx|squareworx/iu', $name) === 1 => 'pokolm_frezy_rohove',
                preg_match('/finworx/iu', $name) === 1 => 'pokolm_frezy_kosoctverce',
                default => 'pokolm_frezy_kulove',
            };
        }
        foreach (self::POKOLM_BY_TAXON as $taxonCode => $key) {
            if (in_array($taxonCode, $taxonCodes, true)) {
                return $key;
            }
        }

        return 'pokolm_prislusenstvi';
    }

    /** @param list<string> $taxonCodes */
    private static function fraisa(array $taxonCodes, string $name): string
    {
        if (in_array('frezy_na_zavity', $taxonCodes, true) || preg_match('/závitov/iu', $name) === 1) {
            return 'fraisa_zavity_frezy';
        }
        if (in_array('tvareci', $taxonCodes, true) || preg_match('/tvářecí/iu', $name) === 1) {
            return 'fraisa_zavity_tvareci';
        }
        if (in_array('rezne', $taxonCodes, true) || preg_match('/závitník/iu', $name) === 1) {
            return preg_match('/x-?tap/iu', $name) === 1 ? 'fraisa_zavity_xtap' : 'fraisa_zavity_zavitniky';
        }
        if (in_array('vrtani', $taxonCodes, true)) {
            return preg_match('/supradrill/iu', $name) === 1 ? 'fraisa_vrtani_supradrill' : 'fraisa_vrtani_dalsi';
        }

        foreach ([...self::FRAISA_MICRO_BY_NAME, ...self::FRAISA_END_MILL_BY_NAME] as $pattern => $key) {
            if (preg_match($pattern, $name) === 1) {
                return $key;
            }
        }

        return 'fraisa_stopkove_dalsi';
    }

    /** @param list<string> $taxonCodes */
    private static function brw(array $taxonCodes, string $name): string
    {
        return match (true) {
            preg_match('/neriox/iu', $name) === 1 => 'brw_neriox',
            in_array('vystruzovani', $taxonCodes, true), preg_match('/výstružník|záhlubník/iu', $name) === 1 => 'brw_vystruzovani',
            default => 'brw_frezovani',
        };
    }
}
