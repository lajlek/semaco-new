<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Infrastructure;

use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class HeliosRateLimiter
{
    public function __construct(private RateLimiterFactory $factory)
    {
    }

    public function consume(int $tokens = 1): void
    {
        $limiter = $this->factory->create('helios');
        $limiter->reserve($tokens)->wait();
    }
}
