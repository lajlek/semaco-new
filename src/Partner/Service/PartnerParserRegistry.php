<?php

declare(strict_types=1);

namespace App\Partner\Service;

use App\Partner\Parser\PartnerParserInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class PartnerParserRegistry
{
    /** @param iterable<PartnerParserInterface> $parsers */
    public function __construct(
        #[AutowireIterator('app.partner_parser')]
        private iterable $parsers,
    ) {
    }

    public function get(string $partnerCode): PartnerParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->partnerCode() === $partnerCode) {
                return $parser;
            }
        }

        throw new \InvalidArgumentException(sprintf('Neznámý partner %s', $partnerCode));
    }
}
