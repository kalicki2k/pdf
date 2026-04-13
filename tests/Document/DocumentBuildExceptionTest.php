<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentBuildExceptionTest extends TestCase
{
    public function testItAddsAUnicodeFontHintOnlyForProfilesThatRequireUnicodeFonts(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA2u()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_UNICODE_FONTS_REQUIRED,
                'Profile PDF/A-2u requires embedded Unicode fonts. Found simple embedded font "Subset" on page 1.',
            ),
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
            new DocumentValidationException(
                DocumentBuildError::TAGGED_PDF_REQUIRED,
                'Profile PDF/A-3a requires structured content in the current implementation.',
            ),
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
            new DocumentValidationException(
                DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED,
                'Profile PDF/A-2u does not allow AcroForm fields in the current PDF/A-2/3 scope.',
            ),
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
            new DocumentValidationException(
                DocumentBuildError::PDFA_ATTACHMENT_MIME_TYPE_REQUIRED,
                'Profile PDF/A-3b requires an embedded file MIME type for attachment 1.',
            ),
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
            new DocumentValidationException(
                DocumentBuildError::PDFA_TRANSPARENCY_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow soft-mask image transparency for image resource 1 on page 1.',
            ),
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
            new DocumentValidationException(
                DocumentBuildError::PDFA4_METADATA_INVALID,
                'Profile PDF/A-4 metadata stream must serialize <pdfaid:rev>2020</pdfaid:rev>.',
            ),
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
            new DocumentValidationException(
                DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID,
                'PDF/A output intent "Press Condition" is not plausible for an RGB ICC profile.',
            ),
        );

        self::assertSame(
            'Use the default PDF/A output intent or pass ->pdfaOutputIntent(...) with a readable ICC profile that matches the document color usage.',
            $exception->hint,
        );
    }

    public function testItAddsALowLevelPdfAHintForCodedLowLevelValidationErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_LOW_LEVEL_CONTENT_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow low-level PDF operator "gs" in page content stream on page 1.',
            ),
        );

        self::assertSame(
            'Use the high-level document APIs instead of raw PDF dictionary or content stream injections for this profile.',
            $exception->hint,
        );
    }

    public function testItAddsAnObjectGraphHintForCodedPdfAObjectGraphErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                'PDF/A-1 tagged catalog requires a StructTreeRoot object ID.',
            ),
        );

        self::assertSame(
            'Keep the generated PDF/A object graph on the validated serializer path; avoid custom low-level object wiring that bypasses the builder and validator invariants.',
            $exception->hint,
        );
    }

    public function testItAddsATaggedStructureHintForCodedTaggedStructureErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged StructTreeRoot must reference ParentTree 9 0 R.',
            ),
        );

        self::assertSame(
            'Use beginStructure()/endStructure() consistently and keep the tagged reading order, ParentTree, MCIDs and StructElem hierarchy on the validated tagged PDF path.',
            $exception->hint,
        );
    }

    public function testItAddsAMetadataConsistencyHintForCodedMetadataErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b(), title: 'Archive Copy'),
            new DocumentValidationException(
                DocumentBuildError::PDFA_METADATA_INCONSISTENT,
                'Profile PDF/A-1b requires consistent Info/XMP metadata for Title.',
            ),
        );

        self::assertSame(
            'Keep Info and XMP metadata synchronized for this profile; update title, author, subject, language and timestamps through the document builder instead of low-level metadata overrides.',
            $exception->hint,
        );
    }

    public function testItAddsAnActionHintForCodedPdfAActionErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ACTION_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow remote outline actions such as GoToR in outline 1.',
            ),
        );

        self::assertSame(
            'Use only PDF/A-safe navigation: internal destinations instead of remote or URI actions, and avoid action dictionaries that the active profile forbids.',
            $exception->hint,
        );
    }

    public function testItAddsATaggedStructureHintForSupportedStructureGuardErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'Profile PDF/A-1a does not allow empty tagged lists. Tagged list 1 has no items.',
            ),
        );

        self::assertSame(
            'Use beginStructure()/endStructure() consistently and keep the tagged reading order, ParentTree, MCIDs and StructElem hierarchy on the validated tagged PDF path.',
            $exception->hint,
        );
    }

    public function testItFallsBackToLegacyStringMatchingForUnconvertedValidationErrors(): void
    {
        $exception = DocumentBuildException::fromValidationFailure(
            new Document(profile: Profile::pdfA3b()),
            new InvalidArgumentException('Profile PDF/A-3b requires embedded fonts. Found standard font "Helvetica" on page 1.'),
        );

        self::assertSame(
            'Use embedded fonts via TextOptions(embeddedFont: ...), table text options, or switch to a non-PDF/A profile.',
            $exception->hint,
        );
    }
}
