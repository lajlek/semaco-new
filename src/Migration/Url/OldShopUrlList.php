<?php

declare(strict_types=1);

namespace App\Migration\Url;

use App\Migration\OldShop\OldShopConnectionFactory;

final readonly class OldShopUrlList
{
    private const STATIC_PATHS = [
        '/',
        '/%s/',
        '/%s/login',
        '/%s/register',
        '/%s/registration-request',
        '/%s/contact/',
        '/%s/forgotten-password',
        '/.well-known/change-password',
        '/%s/cart/',
        '/%s/search?bitbag_elasticsearch_search%%5Bbox%%5D%%5Bquery%%5D=fr%%C3%%A9za',
        '/%s/auto-complete/product?query=freza',
        '/wishlist',
        '/%s/terms/obchodni-podminky',
    ];

    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private string $localeCode,
    ) {
    }

    /** @return list<string> */
    public function paths(): array
    {
        $paths = array_map(fn (string $path): string => sprintf($path, $this->localeCode), self::STATIC_PATHS);

        foreach ($this->strings(
            'SELECT t.slug FROM sylius_product p JOIN sylius_product_translation t ON t.translatable_id = p.id AND t.locale = ? WHERE p.enabled = 1',
            [$this->localeCode],
        ) as $slug) {
            $paths[] = sprintf('/%s/products/%s', $this->localeCode, $slug);
        }

        foreach ($this->strings(
            'SELECT t.slug FROM sylius_taxon x JOIN sylius_taxon_translation t ON t.translatable_id = x.id AND t.locale = ? WHERE x.parent_id IS NOT NULL AND x.enabled = 1',
            [$this->localeCode],
        ) as $slug) {
            $paths[] = sprintf('/%s/products-list/%s', $this->localeCode, $slug);
            $paths[] = sprintf('/%s/taxons/%s', $this->localeCode, $slug);
        }

        foreach ($this->strings(
            'SELECT t.slug FROM bitbag_cms_page p JOIN bitbag_cms_page_translation t ON t.translatable_id = p.id AND t.locale = ? WHERE p.enabled = 1',
            [$this->localeCode],
        ) as $slug) {
            $paths[] = sprintf('/%s/page/%s', $this->localeCode, $slug);
        }

        foreach ($this->strings('SELECT code FROM mergocz_brand') as $code) {
            $paths[] = sprintf('/%s/brand/%s', $this->localeCode, $code);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param list<string> $params
     *
     * @return list<string>
     */
    private function strings(string $sql, array $params = []): array
    {
        return array_values(array_filter($this->oldShop->getConnection()->fetchFirstColumn($sql, $params), 'is_string'));
    }
}
