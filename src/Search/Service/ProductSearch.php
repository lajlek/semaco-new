<?php

declare(strict_types=1);

namespace App\Search\Service;

use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class ProductSearch
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return Pagerfanta<Product> */
    public function search(string $query, ChannelInterface $channel, string $localeCode, int $page, int $limit): Pagerfanta
    {
        /** @var QueryAdapter<Product> $adapter */
        $adapter = new QueryAdapter($this->queryBuilder($query, $channel, $localeCode), false, false);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage(max(1, min($page, max(1, $pager->getNbPages()))));

        return $pager;
    }

    /** @return list<Product> */
    public function suggest(string $query, ChannelInterface $channel, string $localeCode, int $limit): array
    {
        /** @var list<Product> $products */
        $products = $this->queryBuilder($query, $channel, $localeCode)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $products;
    }

    public static function normalize(string $query): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $query));
    }

    private function queryBuilder(string $query, ChannelInterface $channel, string $localeCode): QueryBuilder
    {
        $query = self::normalize($query);
        $words = array_values(array_filter(explode(' ', $query), static fn (string $word): bool => $word !== ''));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->innerJoin('p.translations', 'pt', 'WITH', 'pt.locale = :locale')
            ->where('p.enabled = true')
            ->andWhere(':channel MEMBER OF p.channels')
            ->setParameter('locale', $localeCode)
            ->setParameter('channel', $channel)
            ->orderBy('pt.name', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        $exactMatches = (int) $this->entityManager->createQuery(
            'SELECT COUNT(v.id) FROM App\Entity\Product\ProductVariant v WHERE v.enabled = true AND v.regcisCompact = :exact',
        )->setParameter('exact', str_replace(' ', '', $query))->getSingleScalarResult();
        if ($exactMatches > 0) {
            return $qb
                ->andWhere('EXISTS(SELECT 1 FROM App\Entity\Product\ProductVariant ve WHERE ve.product = p AND ve.enabled = true AND ve.regcisCompact = :exact)')
                ->setParameter('exact', str_replace(' ', '', $query));
        }

        $allWords = [];
        foreach ($words as $index => $word) {
            $allWords[] = sprintf(
                '(pt.name LIKE :word%1$d OR p.code LIKE :word%1$d OR EXISTS(SELECT 1 FROM App\Entity\Product\ProductVariant v%1$d LEFT JOIN v%1$d.translations vt%1$d WITH vt%1$d.locale = :locale WHERE v%1$d.product = p AND v%1$d.enabled = true AND (v%1$d.regcis LIKE :word%1$d OR v%1$d.code LIKE :word%1$d OR vt%1$d.name LIKE :word%1$d)))',
                $index,
            );
            $qb->setParameter('word' . $index, '%' . addcslashes($word, '%_') . '%');
        }

        $qb->andWhere(sprintf(
            '(%s) OR EXISTS(SELECT 1 FROM App\Entity\Product\ProductVariant vc WHERE vc.product = p AND vc.enabled = true AND vc.regcisCompact LIKE :compact)',
            $allWords === [] ? '1 = 0' : implode(' AND ', $allWords),
        ))->setParameter('compact', '%' . addcslashes(str_replace(' ', '', $query), '%_') . '%');

        return $qb;
    }
}
