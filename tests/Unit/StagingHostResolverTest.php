<?php

declare(strict_types=1);

namespace Casablanca\Booking\Tests\Unit;

use Casablanca\Booking\Infrastructure\StagingHostResolver;
use PHPUnit\Framework\TestCase;

final class StagingHostResolverTest extends TestCase
{
    public function testProductionLeavesCustomDomainUntouched(): void
    {
        $resolver = new StagingHostResolver();
        $url = 'https://book.example.com/de/tenant/space';

        self::assertSame($url, $resolver->applyToIbeUrl($url));
    }

    public function testDefaultModeIsProduction(): void
    {
        $resolver = new StagingHostResolver();
        self::assertSame(StagingHostResolver::MODE_PRODUCTION, $resolver->getEnvironmentMode());
    }
}
