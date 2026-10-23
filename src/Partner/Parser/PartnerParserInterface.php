<?php

declare(strict_types=1);

namespace App\Partner\Parser;

use App\Partner\Dto\ParsedPage;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.partner_parser')]
interface PartnerParserInterface
{
    public function partnerCode(): string;

    public function supportsAutomaticFetching(): bool;

    public function parse(string $html, string $pageUrl): ParsedPage;
}
