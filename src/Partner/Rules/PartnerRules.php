<?php

declare(strict_types=1);

namespace App\Partner\Rules;

final class PartnerRules
{
    /** @var array<string, PartnerRule>|null */
    private ?array $rules = null;

    public function get(string $partnerCode): PartnerRule
    {
        return $this->all()[$partnerCode] ?? throw new \InvalidArgumentException(sprintf('Neznámý partner %s', $partnerCode));
    }

    /** @return array<string, PartnerRule> */
    public function all(): array
    {
        return $this->rules ??= [
            'pokolm' => new PartnerRule(
                partnerCode: 'pokolm',
                label: 'POKOLM',
                brandName: 'POKOLM',
                familyPattern: '/(\d+)$/',
                options: [
                    'd1' => 'option_group_1',
                    'l2' => 'option_group_3',
                    'l3' => 'option_group_28',
                    'd2' => 'option_group_24',
                    'd3' => 'option_group_25',
                    'z' => 'option_group_20',
                ],
                extract: [],
                variantNameTemplate: 'Průměr {option_group_1}',
                allowedHosts: [],
            ),
            'fraisa' => new PartnerRule(
                partnerCode: 'fraisa',
                label: 'FRAISA',
                brandName: 'FRAISA',
                familyPattern: '/^(.+)\d{3}$/',
                options: [
                    'd1' => 'option_group_1',
                    'd2' => 'option_group_24',
                    'l1' => 'option_group_22',
                    'l2' => 'option_group_3',
                    'z' => 'option_group_20',
                ],
                extract: [],
                variantNameTemplate: 'Průměr {option_group_1}',
                allowedHosts: ['webshop.fraisa.com'],
            ),
            'brw' => new PartnerRule(
                partnerCode: 'brw',
                label: 'BRW',
                brandName: 'BRW',
                familyPattern: null,
                options: [
                    'd1 [mm]' => 'option_group_1',
                    'r [mm]' => 'option_group_21',
                    'l2' => 'option_group_3',
                    'l2 [mm]' => 'option_group_3',
                    'l3 [mm]' => 'option_group_28',
                    'l1 [mm]' => 'option_group_22',
                    'd2 [mm]' => 'option_group_24',
                    'Z' => 'option_group_20',
                    'Toleranz [mm]' => 'option_group_11',
                ],
                extract: ['spec' => '/^Ø[\d.]+ x r[\d.]+ x (?<l2>[\d.]+)(?: x [\d.]+)?$/u'],
                variantNameTemplate: 'Průměr {option_group_1} L3={option_group_28}',
                allowedHosts: ['www.brw.ch'],
                sitemapUrl: 'https://www.brw.ch/storage/sitemap.xml',
                listingUrlPattern: '#/de-CH/[^/?]+-\d{4,}$#',
                titlePattern: '/\b(?:FUTURO|NERIOX)\b/iu',
            ),
        ];
    }
}
