<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use InvalidArgumentException;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    #[Test]
    public function it_creates_standard_profiles_for_supported_pdf_versions(): void
    {
        self::assertSame(1.4, Profile::standard()->version());
        self::assertSame(1.7, Profile::standard(1.7)->version());
        self::assertSame(2.0, Profile::standard(2.0)->version());
    }

    #[Test]
    public function it_rejects_unsupported_standard_pdf_versions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported PDF version. Supported versions are 1.0 to 1.7 and 2.0.');

        Profile::standard(1.8);
    }

    #[Test]
    public function it_exposes_the_base_versions_for_pdf_a_profiles(): void
    {
        self::assertSame(1.4, Profile::pdfA1b()->version());
        self::assertSame(1.7, Profile::pdfA2u()->version());
        self::assertSame(1.7, Profile::pdfA3u()->version());
        self::assertSame(2.0, Profile::pdfA4f()->version());
    }
}
