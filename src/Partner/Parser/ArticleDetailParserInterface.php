<?php

declare(strict_types=1);

namespace App\Partner\Parser;

interface ArticleDetailParserInterface
{
    /** @return array<string, string> source parameter => value */
    public function parseArticleDetails(string $html): array;
}
