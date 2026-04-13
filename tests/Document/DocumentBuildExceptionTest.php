<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentBuildExceptionTest extends TestCase
{
    public function testItAddsAUnicodeFontHintOnlyForProfilesThatRequireUnicodeFonts(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA2u()),
            new InvalidArgumentException('Profile PDF/A-2u requires embedded Unicode fonts. Found simple embedded font "Subset" on page 1.'),
        );

        self::assertSame(
            'Use an embedded Unicode-capable font and render the affected text with embeddedFont instead of a standard PDF font.',
            $exception->hint,
        );
    }

    public function testItDoesNotAddAUnicodeFontHintForProfilesWithoutThatRequirement(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b()),
            new InvalidArgumentException('Profile PDF/A-1b requires embedded Unicode fonts. Found simple embedded font "Subset" on page 1.'),
        );

        self::assertNull($exception->hint);
    }

    public function testItAddsATaggedPdfHintOnlyForProfilesThatRequireTaggedPdf(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA3a()),
            new InvalidArgumentException('Profile PDF/A-3a requires structured content in the current implementation.'),
        );

        self::assertSame(
            'Use beginStructure()/endStructure() for containers and TextOptions(tag: ...) for leaf roles.',
            $exception->hint,
        );
    }

    public function testItDoesNotAddATaggedPdfHintForNonTaggedProfiles(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA3b()),
            new InvalidArgumentException('Profile PDF/A-3b requires structured content in the current implementation.'),
        );

        self::assertNull($exception->hint);
    }

    public function testItAddsAnAcroFormHintForBlockedPdfA23Profiles(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA2u()),
            new InvalidArgumentException('Profile PDF/A-2u does not allow AcroForm fields in the current PDF/A-2/3 scope.'),
        );

        self::assertSame(
            'Remove AcroForm fields for this profile, or switch to a non-PDF/A profile. Only the constrained PDF/A-1a form scope is currently supported.',
            $exception->hint,
        );
    }

    public function testItAddsAnAttachmentMimeTypeHintWhenAttachmentsAreAllowed(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA3b()),
            new InvalidArgumentException('Profile PDF/A-3b requires an embedded file MIME type for attachment 1.'),
        );

        self::assertSame(
            'Set a MIME type on each EmbeddedFile so the attachment can be serialized as a valid associated file.',
            $exception->hint,
        );
    }

    public function testItAddsATransparencyHintWhenTheProfileBlocksTransparency(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b()),
            new InvalidArgumentException('Profile PDF/A-1b does not allow soft-mask image transparency for image resource 1 on page 1.'),
        );

        self::assertSame(
            'Remove soft masks from image resources or flatten transparency before rendering in this profile.',
            $exception->hint,
        );
    }

    public function testItAddsAPdfA4MetadataHintForPdfA4ObjectGraphFailures(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA4()),
            new InvalidArgumentException('Profile PDF/A-4 metadata stream must serialize <pdfaid:rev>2020</pdfaid:rev>.'),
        );

        self::assertSame(
            'Keep PDF/A-4 metadata on the dedicated PDF 2.0 path: write pdfaid:part=4 and pdfaid:rev=2020, omit Info/OutputIntents, and only write pdfaid:conformance for 4e/4f.',
            $exception->hint,
        );
    }

    public function testItAddsAnOutputIntentHintForPdfAColorFailures(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b()),
            new InvalidArgumentException('PDF/A output intent "Press Condition" is not plausible for an RGB ICC profile.'),
        );

        self::assertSame(
            'Use the default PDF/A output intent or pass ->pdfaOutputIntent(...) with a readable ICC profile that matches the document color usage.',
            $exception->hint,
        );
    }
}
