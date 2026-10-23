<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Cms\Entity\CmsPage;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 70)]
final readonly class CmsPageMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        private string $localeCode,
    ) {
    }

    public function name(): string
    {
        return 'cms-pages';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('cms-pages');

        /** @var list<array{id: int|string, code: string, enabled: int|string, slug: string|null, name: string|null, title: string|null, content: string|null, meta_description: string|null}> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT p.id, p.code, p.enabled, t.slug, t.name, t.title, t.content, t.meta_description
             FROM bitbag_cms_page p JOIN bitbag_cms_page_translation t ON t.translatable_id = p.id AND t.locale = ?
             ORDER BY p.id ASC',
            [$this->localeCode],
        );

        foreach ($rows as $position => $row) {
            if ($row['slug'] === null) {
                $result->skipped();

                continue;
            }

            $page = $this->entityManager->getRepository(CmsPage::class)->findOneBy(['code' => $row['code']]);
            if (!$page instanceof CmsPage) {
                $page = new CmsPage();
                $page->setCode($row['code']);
                $this->entityManager->persist($page);
                $result->created();
            } else {
                $result->updated();
            }

            $page->setSlug($row['slug']);
            $page->setTitle((string) ($row['name'] ?? $row['slug']));
            $page->setMetaTitle($row['title']);
            $page->setMetaDescription($row['meta_description'] === null ? null : mb_substr($row['meta_description'], 0, 500));
            $page->setContent($row['content']);
            $page->setEnabled((bool) $row['enabled']);
            $page->setPosition($position);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
