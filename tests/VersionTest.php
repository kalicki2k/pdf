<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use Kalle\Pdf\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testAllReturnsAllSupportedVersionsInOrder(): void
    {
        self::assertSame([
            Version::V1_0,
            Version::V1_1,
            Version::V1_2,
            Version::V1_3,
            Version::V1_4,
            Version::V1_5,
            Version::V1_6,
            Version::V1_7,
            Version::V2_0,
        ], Version::all());
    }

    public function testAllDoesNotContainDuplicates(): void
    {
        self::assertCount(
            count(array_unique(Version::all())),
            Version::all(),
        );
    }

    public function testFormatFormatsVersionNumbers(): void
    {
        self::assertSame('1.0', Version::format(Version::V1_0));
        self::assertSame('1.7', Version::format(Version::V1_7));
        self::assertSame('2.0', Version::format(Version::V2_0));
    }
}
