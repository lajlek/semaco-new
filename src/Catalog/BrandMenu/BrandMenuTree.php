<?php

declare(strict_types=1);

namespace App\Catalog\BrandMenu;

final class BrandMenuTree
{
    public const ROOT_CODE = 'vyrobci';

    public const ROOT_NAME = 'Výrobci';

    public const ROOT_SLUG = 'vyrobci';

    public const POKOLM = 'pokolm';

    public const FRAISA = 'fraisa';

    public const BRW = 'brw';

    public const OTHER = 'ostatni';

    private const CODE_PREFIX = 'vyrobci_';

    /** @var array<string, array{name: string, segment: string, parent: string|null}> */
    private const NODES = [
        self::POKOLM => ['name' => 'POKOLM', 'segment' => 'pokolm', 'parent' => null],
        'pokolm_frezy' => ['name' => 'Frézy s VBD', 'segment' => 'frezy-s-vbd', 'parent' => self::POKOLM],
        'pokolm_frezy_rovinne' => ['name' => 'Rovinné frézy', 'segment' => 'rovinne', 'parent' => 'pokolm_frezy'],
        'pokolm_frezy_rohove' => ['name' => 'Rohové, drážkovací a srážecí frézy', 'segment' => 'rohove-drazkovaci-srazeci', 'parent' => 'pokolm_frezy'],
        'pokolm_frezy_kopirovaci' => ['name' => 'Kopírovací frézy', 'segment' => 'kopirovaci', 'parent' => 'pokolm_frezy'],
        'pokolm_frezy_kulove' => ['name' => 'Kulové a toroidní frézy', 'segment' => 'kulove-toroidni', 'parent' => 'pokolm_frezy'],
        'pokolm_frezy_posuv' => ['name' => 'Frézy pro vysoký posuv', 'segment' => 'vysoky-posuv', 'parent' => 'pokolm_frezy'],
        'pokolm_frezy_kosoctverce' => ['name' => 'Kosočtvercové frézy', 'segment' => 'kosoctvercove', 'parent' => 'pokolm_frezy'],
        'pokolm_desticky' => ['name' => 'Břitové destičky', 'segment' => 'britove-desticky', 'parent' => self::POKOLM],
        'pokolm_upinani' => ['name' => 'Upínací systémy', 'segment' => 'upinaci-systemy', 'parent' => self::POKOLM],
        'pokolm_upinani_hsk' => ['name' => 'HSK', 'segment' => 'hsk', 'parent' => 'pokolm_upinani'],
        'pokolm_upinani_sk' => ['name' => 'Strmý kužel (SK)', 'segment' => 'strmy-kuzel', 'parent' => 'pokolm_upinani'],
        'pokolm_upinani_bt' => ['name' => 'BT', 'segment' => 'bt', 'parent' => 'pokolm_upinani'],
        'pokolm_upinani_coolcap' => ['name' => 'Coolcap', 'segment' => 'coolcap', 'parent' => 'pokolm_upinani'],
        'pokolm_upinani_plananlage' => ['name' => 'Plananlage', 'segment' => 'plananlage', 'parent' => 'pokolm_upinani'],
        'pokolm_upinani_adaptery' => ['name' => 'Adaptéry a příslušenství', 'segment' => 'adaptery', 'parent' => 'pokolm_upinani'],
        'pokolm_prislusenstvi' => ['name' => 'Příslušenství', 'segment' => 'prislusenstvi', 'parent' => self::POKOLM],

        self::FRAISA => ['name' => 'FRAISA', 'segment' => 'fraisa', 'parent' => null],
        'fraisa_stopkove' => ['name' => 'Stopkové frézy', 'segment' => 'stopkove-frezy', 'parent' => self::FRAISA],
        'fraisa_stopkove_ax' => ['name' => 'AX', 'segment' => 'ax', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_nx' => ['name' => 'NX', 'segment' => 'nx', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_sx' => ['name' => 'SX', 'segment' => 'sx', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_hx' => ['name' => 'HX a XSpeed-H', 'segment' => 'hx-xspeed-h', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_ecut' => ['name' => 'E-Cut', 'segment' => 'e-cut', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_mfc' => ['name' => 'MFC', 'segment' => 'mfc', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_xfeed' => ['name' => 'XFeed', 'segment' => 'xfeed', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_spherox' => ['name' => 'SpheroX', 'segment' => 'spherox', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_sphericut' => ['name' => 'Sphericut', 'segment' => 'sphericut', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_arcut' => ['name' => 'ArCut X', 'segment' => 'arcut-x', 'parent' => 'fraisa_stopkove'],
        'fraisa_stopkove_dalsi' => ['name' => 'Další stopkové frézy', 'segment' => 'dalsi', 'parent' => 'fraisa_stopkove'],
        'fraisa_mikro' => ['name' => 'Mikrofrézy', 'segment' => 'mikrofrezy', 'parent' => self::FRAISA],
        'fraisa_mikro_microcut' => ['name' => 'Microcut', 'segment' => 'microcut', 'parent' => 'fraisa_mikro'],
        'fraisa_mikro_microx' => ['name' => 'MicroX', 'segment' => 'microx', 'parent' => 'fraisa_mikro'],
        'fraisa_mikro_microhx' => ['name' => 'MicroHX', 'segment' => 'microhx', 'parent' => 'fraisa_mikro'],
        'fraisa_mikro_xg' => ['name' => 'MicrospheroXG a MicrotoroXG', 'segment' => 'microspheroxg-microtoroxg', 'parent' => 'fraisa_mikro'],
        'fraisa_vrtani' => ['name' => 'Vrtací nástroje', 'segment' => 'vrtaci-nastroje', 'parent' => self::FRAISA],
        'fraisa_vrtani_supradrill' => ['name' => 'Supradrill', 'segment' => 'supradrill', 'parent' => 'fraisa_vrtani'],
        'fraisa_vrtani_dalsi' => ['name' => 'Další vrtací nástroje', 'segment' => 'dalsi', 'parent' => 'fraisa_vrtani'],
        'fraisa_zavity' => ['name' => 'Závitovací nástroje', 'segment' => 'zavitovaci-nastroje', 'parent' => self::FRAISA],
        'fraisa_zavity_xtap' => ['name' => 'Xtap', 'segment' => 'xtap', 'parent' => 'fraisa_zavity'],
        'fraisa_zavity_zavitniky' => ['name' => 'Další závitníky', 'segment' => 'zavitniky', 'parent' => 'fraisa_zavity'],
        'fraisa_zavity_tvareci' => ['name' => 'Tvářecí závitníky', 'segment' => 'tvareci', 'parent' => 'fraisa_zavity'],
        'fraisa_zavity_frezy' => ['name' => 'Závitové frézy', 'segment' => 'zavitove-frezy', 'parent' => 'fraisa_zavity'],

        self::BRW => ['name' => 'BRW', 'segment' => 'brw', 'parent' => null],
        'brw_frezovani' => ['name' => 'FUTURO frézování, vrtání, závitování', 'segment' => 'futuro-frezovani-vrtani-zavitovani', 'parent' => self::BRW],
        'brw_frezovani_hrubovaci' => ['name' => 'Frézy VHM s hrubovacím profilem', 'segment' => 'vhm-hrubovaci', 'parent' => 'brw_frezovani'],
        'brw_frezovani_dokoncovaci' => ['name' => 'Frézy VHM s dokončovacím profilem', 'segment' => 'vhm-dokoncovaci', 'parent' => 'brw_frezovani'],
        'brw_frezovani_tvarove' => ['name' => 'Frézy VHM srážecí, gravírovací a tvarové', 'segment' => 'vhm-srazeci-gravirovaci-tvarove', 'parent' => 'brw_frezovani'],
        'brw_frezovani_diamant' => ['name' => 'Frézy VHM s diamantovým povlakem', 'segment' => 'vhm-diamantovy-povlak', 'parent' => 'brw_frezovani'],
        'brw_frezovani_mikro' => ['name' => 'Mikrofrézy VHM', 'segment' => 'vhm-mikrofrezy', 'parent' => 'brw_frezovani'],
        'brw_frezovani_formy' => ['name' => 'Frézy VHM pro výrobu forem a nástrojů', 'segment' => 'vhm-formy-nastroje', 'parent' => 'brw_frezovani'],
        'brw_frezovani_hss' => ['name' => 'Frézy HSS', 'segment' => 'frezy-hss', 'parent' => 'brw_frezovani'],
        'brw_frezovani_vbd' => ['name' => 'Frézy s VBD', 'segment' => 'frezy-vbd', 'parent' => 'brw_frezovani'],
        'brw_frezovani_pilniky_hm' => ['name' => 'Rotační pilníky HM', 'segment' => 'rotacni-pilniky-hm', 'parent' => 'brw_frezovani'],
        'brw_frezovani_pilniky_vhm' => ['name' => 'Rotační pilníky VHM', 'segment' => 'rotacni-pilniky-vhm', 'parent' => 'brw_frezovani'],
        'brw_frezovani_navrtavaci' => ['name' => 'Navrtávací vrtáky NC', 'segment' => 'navrtavaci-vrtaky-nc', 'parent' => 'brw_frezovani'],
        'brw_frezovani_din338' => ['name' => 'Spirálové vrtáky HSS DIN 338', 'segment' => 'spiralove-vrtaky-hss-din-338', 'parent' => 'brw_frezovani'],
        'brw_frezovani_din1897' => ['name' => 'Spirálové vrtáky HSS DIN 1897', 'segment' => 'spiralove-vrtaky-hss-din-1897', 'parent' => 'brw_frezovani'],
        'brw_frezovani_din340' => ['name' => 'Spirálové vrtáky HSS DIN 340', 'segment' => 'spiralove-vrtaky-hss-din-340', 'parent' => 'brw_frezovani'],
        'brw_frezovani_din1869' => ['name' => 'Spirálové vrtáky HSS DIN 1869', 'segment' => 'spiralove-vrtaky-hss-din-1869', 'parent' => 'brw_frezovani'],
        'brw_frezovani_din345' => ['name' => 'Vrtáky s Morseovým kuželem DIN 345', 'segment' => 'vrtaky-morse-din-345', 'parent' => 'brw_frezovani'],
        'brw_frezovani_sady' => ['name' => 'Sady spirálových vrtáků HSS', 'segment' => 'sady-spiralovych-vrtaku-hss', 'parent' => 'brw_frezovani'],
        'brw_frezovani_vrtaky_vhm' => ['name' => 'Spirálové vrtáky VHM', 'segment' => 'spiralove-vrtaky-vhm', 'parent' => 'brw_frezovani'],
        'brw_frezovani_vrtaky_vbd' => ['name' => 'Vrtáky s VBD', 'segment' => 'vrtaky-vbd', 'parent' => 'brw_frezovani'],
        'brw_frezovani_stupnovite' => ['name' => 'Stupňovité vrtáky', 'segment' => 'stupnovite-vrtaky', 'parent' => 'brw_frezovani'],
        'brw_frezovani_zavitniky' => ['name' => 'Závitníky', 'segment' => 'zavitniky', 'parent' => 'brw_frezovani'],
        'brw_vystruzovani' => ['name' => 'FUTURO zahlubování, vystružování, soustružení', 'segment' => 'futuro-zahlubovani-vystruzovani-soustruzeni', 'parent' => self::BRW],
        'brw_vystruzovani_zahlubniky' => ['name' => 'Kuželové záhlubníky', 'segment' => 'kuzelove-zahlubniky', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_hss' => ['name' => 'Výstružníky HSS', 'segment' => 'vystruzniky-hss', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_vhm' => ['name' => 'Výstružníky VHM', 'segment' => 'vystruzniky-vhm', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_vyvrtavaci' => ['name' => 'Vyvrtávací nástroje', 'segment' => 'vyvrtavaci-nastroje', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_soustruzeni' => ['name' => 'Soustružnické nástroje', 'segment' => 'soustruznicke-nastroje', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_desticky' => ['name' => 'Vyměnitelné břitové destičky', 'segment' => 'vymenitelne-britove-desticky', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_multifunkcni' => ['name' => 'Multifunkční nástroje', 'segment' => 'multifunkcni-nastroje', 'parent' => 'brw_vystruzovani'],
        'brw_vystruzovani_nahradni_dily' => ['name' => 'Náhradní díly', 'segment' => 'nahradni-dily', 'parent' => 'brw_vystruzovani'],
        'brw_neriox' => ['name' => 'NERIOX', 'segment' => 'neriox', 'parent' => self::BRW],
        'brw_neriox_frezy_vhm' => ['name' => 'Frézy VHM', 'segment' => 'frezy-vhm', 'parent' => 'brw_neriox'],
        'brw_neriox_frezy_hss' => ['name' => 'Frézy HSS', 'segment' => 'frezy-hss', 'parent' => 'brw_neriox'],
        'brw_neriox_pilniky' => ['name' => 'Rotační pilníky HM', 'segment' => 'rotacni-pilniky-hm', 'parent' => 'brw_neriox'],
        'brw_neriox_vrtaky_vhm' => ['name' => 'Vrtáky VHM', 'segment' => 'vrtaky-vhm', 'parent' => 'brw_neriox'],
        'brw_neriox_vrtaky_hss' => ['name' => 'Vrtáky HSS', 'segment' => 'vrtaky-hss', 'parent' => 'brw_neriox'],
        'brw_neriox_zahlubniky' => ['name' => 'Záhlubníky', 'segment' => 'zahlubniky', 'parent' => 'brw_neriox'],
        'brw_neriox_zavitniky' => ['name' => 'Závitníky a závitová očka HSS', 'segment' => 'zavitniky-ocka-hss', 'parent' => 'brw_neriox'],
        'brw_neriox_vystruzniky' => ['name' => 'Výstružníky', 'segment' => 'vystruzniky', 'parent' => 'brw_neriox'],
        'brw_neriox_upinace' => ['name' => 'Upínače nástrojů', 'segment' => 'upinace-nastroju', 'parent' => 'brw_neriox'],
        'brw_neriox_klestiny' => ['name' => 'Upínací kleštiny', 'segment' => 'upinaci-klestiny', 'parent' => 'brw_neriox'],
        'brw_neriox_hroty' => ['name' => 'Soustružnické hroty', 'segment' => 'soustruznicke-hroty', 'parent' => 'brw_neriox'],
        'brw_neriox_redukce' => ['name' => 'Redukční pouzdra a vložky', 'segment' => 'redukcni-pouzdra-vlozky', 'parent' => 'brw_neriox'],
        'brw_neriox_sveraky' => ['name' => 'Strojní svěráky', 'segment' => 'strojni-sveraky', 'parent' => 'brw_neriox'],

        self::OTHER => ['name' => 'Ostatní', 'segment' => 'ostatni-vyrobci', 'parent' => null],
        'ostatni_hity' => ['name' => 'Cenové hity', 'segment' => 'cenove-hity', 'parent' => self::OTHER],
        'ostatni_dalsi' => ['name' => 'Další výrobci', 'segment' => 'dalsi-vyrobci', 'parent' => self::OTHER],
    ];

    public static function isMenuTaxon(?string $code): bool
    {
        return $code === self::ROOT_CODE || str_starts_with((string) $code, self::CODE_PREFIX);
    }

    public static function code(string $key): string
    {
        return self::CODE_PREFIX . $key;
    }

    /** @return array<string, array{name: string, segment: string, parent: string|null}> */
    public static function nodes(): array
    {
        return self::NODES;
    }

    public static function slug(string $key): string
    {
        $node = self::NODES[$key];

        return $node['parent'] === null ? $node['segment'] : self::slug($node['parent']) . '/' . $node['segment'];
    }

    /** @return list<string> */
    public static function withAncestors(string $key): array
    {
        $keys = [];
        for ($current = $key; $current !== null; $current = self::NODES[$current]['parent']) {
            $keys[] = $current;
        }

        return $keys;
    }
}
