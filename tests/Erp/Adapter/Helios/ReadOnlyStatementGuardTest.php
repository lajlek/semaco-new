<?php

declare(strict_types=1);

namespace App\Tests\Erp\Adapter\Helios;

use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\HeliosWriteForbiddenException;
use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\ReadOnlyStatementGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadOnlyStatementGuardTest extends TestCase
{
    #[DataProvider('allowed')]
    public function testAllowsReads(string $sql): void
    {
        ReadOnlyStatementGuard::assertReadOnly($sql);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('forbidden')]
    public function testRejectsWrites(string $sql): void
    {
        $this->expectException(HeliosWriteForbiddenException::class);

        ReadOnlyStatementGuard::assertReadOnly($sql);
    }

    /** @return iterable<string, array{string}> */
    public static function allowed(): iterable
    {
        yield 'select' => ['SELECT 1'];
        yield 'lowercase with leading whitespace' => ["\n  select * from hvw_SapertaTabSapKmenZbozi where id = ?"];
        yield 'cte' => ['WITH x AS (SELECT 1 AS a) SELECT a FROM x'];
        yield 'keyword inside literal' => ["SELECT * FROM t WHERE nazev = 'INSERT INTO'"];
        yield 'keyword inside comment' => ["SELECT 1 -- delete later\n"];
        yield 'column containing keyword' => ['SELECT updated_at, deleted_flag FROM t'];
    }

    /** @return iterable<string, array{string}> */
    public static function forbidden(): iterable
    {
        yield 'insert' => ['INSERT INTO SapTabObjHla (id) VALUES (1)'];
        yield 'update' => ['update TabCisOrg set nazev = ? where id = 1'];
        yield 'delete' => ['DELETE FROM SapTabObjPol'];
        yield 'exec procedure' => ['EXEC dbo.ar_importdokladuzeshopu @selectem=1'];
        yield 'select into' => ['SELECT * INTO #tmp FROM TabCisOrg'];
        yield 'stacked statement' => ['SELECT 1; DROP TABLE x'];
        yield 'comment hiding prefix' => ['/* SELECT */ DELETE FROM t'];
    }
}
