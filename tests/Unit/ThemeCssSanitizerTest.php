<?php

declare(strict_types=1);

namespace Casablanca\Booking\Tests\Unit;

use Casablanca\Booking\Domain\Utility\ThemeCssSanitizer;
use PHPUnit\Framework\TestCase;

final class ThemeCssSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        self::assertSame('.cb-widget{color:red}', ThemeCssSanitizer::sanitize('.cb-widget{color:red}<script'));
    }
}
