<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use InvalidArgumentException;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('namedStandardProfileProvider')]
    public function it_creates_named_standard_profiles_for_all_supported_pdf_versions(
        string $factory,
        float $expectedVersion,
    ): void {
        $profile = Profile::{$factory}();

        self::assertSame('standard', $profile->name());
        self::assertTrue($profile->isStandard());
        self::assertSame($expectedVersion, $profile->version());
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
        self::assertSame(1.7, Profile::pdfA2a()->version());
        self::assertSame(1.4, Profile::pdfA1b()->version());
        self::assertSame(1.7, Profile::pdfA2b()->version());
        self::assertSame(1.7, Profile::pdfA2u()->version());
        self::assertSame(1.7, Profile::pdfA3a()->version());
        self::assertSame(1.7, Profile::pdfA3u()->version());
        self::assertSame(2.0, Profile::pdfA4f()->version());
    }

    #[Test]
    public function it_detects_pdf_a_part_2_profiles(): void
    {
        self::assertTrue(Profile::pdfA2a()->isPdfA2());
        self::assertTrue(Profile::pdfA2b()->isPdfA2());
        self::assertTrue(Profile::pdfA2u()->isPdfA2());
        self::assertFalse(Profile::pdfA3u()->isPdfA2());
    }

    #[Test]
    public function it_detects_pdf_a_part_3_profiles(): void
    {
        self::assertTrue(Profile::pdfA3b()->isPdfA3());
        self::assertTrue(Profile::pdfA3u()->isPdfA3());
        self::assertFalse(Profile::pdfA2u()->isPdfA3());
    }

    #[Test]
    public function it_detects_pdf_a_part_4_profiles(): void
    {
        self::assertTrue(Profile::pdfA4()->isPdfA4());
        self::assertTrue(Profile::pdfA4e()->isPdfA4());
        self::assertTrue(Profile::pdfA4f()->isPdfA4());
        self::assertFalse(Profile::pdfA3u()->isPdfA4());
    }

    #[Test]
    public function it_detects_pdf_a_4f_profile(): void
    {
        self::assertTrue(Profile::pdfA4f()->isPdfA4f());
        self::assertFalse(Profile::pdfA4()->isPdfA4f());
    }

    #[Test]
    public function it_exposes_the_base_version_for_pdf_a_4e(): void
    {
        self::assertSame(2.0, Profile::pdfA4e()->version());
    }

    #[Test]
    public function it_detects_profiles_that_require_tagged_pdf(): void
    {
        self::assertTrue(Profile::pdfA2a()->requiresTaggedPdf());
        self::assertTrue(Profile::pdfA3a()->requiresTaggedPdf());
        self::assertFalse(Profile::pdfA2u()->requiresTaggedPdf());
    }

    #[Test]
    public function it_detects_profiles_that_support_pdf_1_4_features(): void
    {
        self::assertFalse(Profile::pdf13()->supportsXmpMetadata());
        self::assertFalse(Profile::pdf13()->supportsStructure());
        self::assertFalse(Profile::pdf13()->supportsTransparency());

        self::assertTrue(Profile::pdf14()->supportsXmpMetadata());
        self::assertTrue(Profile::pdf14()->supportsStructure());
        self::assertTrue(Profile::pdf14()->supportsTransparency());

        self::assertTrue(Profile::pdfA1b()->supportsXmpMetadata());
        self::assertTrue(Profile::pdfA1b()->supportsStructure());
        self::assertFalse(Profile::pdfA1b()->supportsTransparency());
    }

    #[Test]
    public function it_detects_profiles_that_support_aes_128_encryption(): void
    {
        self::assertFalse(Profile::pdf15()->supportsAes128Encryption());
        self::assertTrue(Profile::pdf16()->supportsAes128Encryption());
        self::assertFalse(Profile::pdfA2u()->supportsAes128Encryption());
    }

    #[Test]
    public function it_detects_profiles_that_support_optional_content_groups(): void
    {
        self::assertFalse(Profile::pdf14()->supportsOptionalContentGroups());
        self::assertTrue(Profile::pdf15()->supportsOptionalContentGroups());
        self::assertFalse(Profile::pdfA2u()->supportsOptionalContentGroups());
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function namedStandardProfileProvider(): array
    {
        return [
            'PDF 1.0' => ['pdf10', 1.0],
            'PDF 1.1' => ['pdf11', 1.1],
            'PDF 1.2' => ['pdf12', 1.2],
            'PDF 1.3' => ['pdf13', 1.3],
            'PDF 1.4' => ['pdf14', 1.4],
            'PDF 1.5' => ['pdf15', 1.5],
            'PDF 1.6' => ['pdf16', 1.6],
            'PDF 1.7' => ['pdf17', 1.7],
            'PDF 2.0' => ['pdf20', 2.0],
        ];
    }
}
