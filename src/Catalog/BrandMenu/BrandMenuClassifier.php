<?php

declare(strict_types=1);

namespace App\Catalog\BrandMenu;

final class BrandMenuClassifier
{
    public const PRICE_HITS_TAXON = 'cenove_hity';

    /** @var array<string, string> */
    private const MAKER_BY_BRAND = ['POKOLM' => BrandMenuTree::POKOLM, 'FRAISA' => BrandMenuTree::FRAISA, 'BRW' => BrandMenuTree::BRW];

    /** @var array<int, string> */
    private const MAKER_BY_GOODS_GROUP = ['117' => BrandMenuTree::BRW, '120' => BrandMenuTree::FRAISA, '180' => BrandMenuTree::POKOLM];

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

    /** @var array<string, string> */
    private const BRW_REAMING_BY_NAME = [
        '/(?:výstružník|reibahle).*hss|hss.*(?:výstružník|reibahle)/iu' => 'brw_vystruzovani_hss',
        '/výstružník|reibahle/iu' => 'brw_vystruzovani_vhm',
        '/záhlubník|kegelsenker/iu' => 'brw_vystruzovani_zahlubniky',
        '/vyvrtávac|ausdreh/iu' => 'brw_vystruzovani_vyvrtavaci',
        '/soustruž|drehwerkzeug/iu' => 'brw_vystruzovani_soustruzeni',
        '/břitov\w* destičk|wendeplatten?\b/iu' => 'brw_vystruzovani_desticky',
        '/multifunk/iu' => 'brw_vystruzovani_multifunkcni',
        '/náhradní díl|ersatzteil/iu' => 'brw_vystruzovani_nahradni_dily',
    ];

    /** @var array<string, string> */
    private const BRW_MILLING_BY_NAME = [
        '/závitník|gewinden?bohr/iu' => 'brw_frezovani_zavitniky',
        '/sad[ay]\b.*vrták|spiralbohrersortiment/iu' => 'brw_frezovani_sady',
        '/din\s*338/iu' => 'brw_frezovani_din338',
        '/din\s*1897/iu' => 'brw_frezovani_din1897',
        '/din\s*340/iu' => 'brw_frezovani_din340',
        '/din\s*1869/iu' => 'brw_frezovani_din1869',
        '/din\s*345|morse/iu' => 'brw_frezovani_din345',
        '/stupňovit|stufenbohr/iu' => 'brw_frezovani_stupnovite',
        '/navrtáv|anbohrer/iu' => 'brw_frezovani_navrtavaci',
        '/vrták.*vbd|wendeplattenbohr/iu' => 'brw_frezovani_vrtaky_vbd',
        '/(?:vrták|bohrer).*vhm|vhm.*(?:vrták|bohrer)/iu' => 'brw_frezovani_vrtaky_vhm',
        '/(?:pilník|frässtift).*vhm|vhm.*(?:pilník|frässtift)/iu' => 'brw_frezovani_pilniky_vhm',
        '/pilník|frässtift/iu' => 'brw_frezovani_pilniky_hm',
        '/\bvbd\b|wendeplatten/iu' => 'brw_frezovani_vbd',
        '/(?:fréz|fräs).*hss|hss.*(?:fréz|fräs)/iu' => 'brw_frezovani_hss',
        '/mikrofréz|mikrofräs/iu' => 'brw_frezovani_mikro',
        '/diamant/iu' => 'brw_frezovani_diamant',
        '/hrubovac|schrupp/iu' => 'brw_frezovani_hrubovaci',
        '/srážec|gravír|tvarov|fasfräs|gravier|formfräs/iu' => 'brw_frezovani_tvarove',
        '/\bforem\b|formenbau|werkzeugbau/iu' => 'brw_frezovani_formy',
        '/kulov|kugel|radiuskopier|dokončovac|schlicht/iu' => 'brw_frezovani_dokoncovaci',
    ];

    /** @var array<string, string> */
    private const BRW_NERIOX_BY_NAME = [
        '/závitník|závitov\w* očk|gewinden?bohr|schneideisen/iu' => 'brw_neriox_zavitniky',
        '/výstružník|reibahl|reiben/iu' => 'brw_neriox_vystruzniky',
        '/záhlubník|senker/iu' => 'brw_neriox_zahlubniky',
        '/pilník|frässtift/iu' => 'brw_neriox_pilniky',
        '/upínač|werkzeugaufnahm/iu' => 'brw_neriox_upinace',
        '/kleštin|spannzang/iu' => 'brw_neriox_klestiny',
        '/soustružnick\w* hrot|drehbankspitz/iu' => 'brw_neriox_hroty',
        '/redukč|reduzier|einsteckkon/iu' => 'brw_neriox_redukce',
        '/svěrák|schraubstock/iu' => 'brw_neriox_sveraky',
        '/(?:fréz|fräs).*hss|hss.*(?:fréz|fräs)/iu' => 'brw_neriox_frezy_hss',
        '/fréz|fräs/iu' => 'brw_neriox_frezy_vhm',
        '/(?:vrták|bohr).*hss|hss.*(?:vrták|bohr)/iu' => 'brw_neriox_vrtaky_hss',
        '/vrták|bohr/iu' => 'brw_neriox_vrtaky_vhm',
    ];

    /**
     * @param list<string> $taxonCodes
     *
     * @return list<string>
     */
    public static function classify(?string $brandName, ?string $goodsGroup, array $taxonCodes, string $name): array
    {
        $priceHit = in_array(self::PRICE_HITS_TAXON, $taxonCodes, true);
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

        return self::firstMatch([...self::FRAISA_MICRO_BY_NAME, ...self::FRAISA_END_MILL_BY_NAME], $name) ?? 'fraisa_stopkove_dalsi';
    }

    /** @param list<string> $taxonCodes */
    private static function brw(array $taxonCodes, string $name): string
    {
        if (preg_match('/neriox/iu', $name) === 1) {
            return self::firstMatch(self::BRW_NERIOX_BY_NAME, $name) ?? 'brw_neriox';
        }

        return self::firstMatch(self::BRW_REAMING_BY_NAME, $name)
            ?? self::firstMatch(self::BRW_MILLING_BY_NAME, $name)
            ?? (in_array('vystruzovani', $taxonCodes, true) ? 'brw_vystruzovani' : 'brw_frezovani');
    }

    /** @param array<string, string> $rules */
    private static function firstMatch(array $rules, string $name): ?string
    {
        foreach ($rules as $pattern => $key) {
            if (preg_match($pattern, $name) === 1) {
                return $key;
            }
        }

        return null;
    }
}
