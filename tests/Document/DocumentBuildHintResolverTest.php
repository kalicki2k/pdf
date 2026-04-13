<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentBuildHintResolver;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentBuildHintResolverTest extends TestCase
{
    private DocumentBuildHintResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DocumentBuildHintResolver();
    }

    public function testItAddsAHintForDuplicateNamedDestinations(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::DUPLICATE_NAMED_DESTINATION,
                'Named destination "intro" is defined more than once. Duplicate found on page 2.',
            ),
        );

        self::assertSame(
            'Use unique names for each named destination so outlines and links resolve unambiguously.',
            $hint,
        );
    }

    public function testItAddsAHintForDuplicateAttachmentFilenames(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::DUPLICATE_ATTACHMENT_FILENAME,
                'Attachment filename "demo.txt" is used more than once. Duplicate found at attachment 2.',
            ),
        );

        self::assertSame(
            'Give each attachment a unique filename before building the document.',
            $hint,
        );
    }

    public function testItAddsAHintForInvalidOutlineReferences(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::OUTLINE_REFERENCE_INVALID,
                'Outline 1 references page 2, but the document only has 1 page(s).',
            ),
        );

        self::assertSame(
            'Point each outline to an existing page or named destination, and keep remote destinations separate from local page references.',
            $hint,
        );
    }

    public function testItAddsAHintForInvalidOutlineHierarchy(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::OUTLINE_HIERARCHY_INVALID,
                'The first outline must use level 1.',
            ),
        );

        self::assertSame(
            'Start outlines at level 1 and only increase nesting one level at a time.',
            $hint,
        );
    }

    public function testItAddsAHintForInvalidFormFieldPages(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::FORM_FIELD_PAGE_INVALID,
                'Form field "customer_name" targets page 2 which does not exist.',
            ),
        );

        self::assertSame(
            'Attach each form field or radio choice to an existing page in the document.',
            $hint,
        );
    }

    public function testItAddsAHintForMissingTableOfContentsEntries(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::TABLE_OF_CONTENTS_ENTRIES_REQUIRED,
                'Table of contents requires at least one outline or explicit table of contents entry.',
            ),
        );

        self::assertSame(
            'Add at least one outline or explicit table-of-contents entry before building the table of contents.',
            $hint,
        );
    }

    public function testItAddsAHintForInvalidTableOfContentsLayout(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::TABLE_OF_CONTENTS_LAYOUT_INVALID,
                'Table of contents content width must be greater than zero.',
            ),
        );

        self::assertSame(
            'Use page margins and page size that leave positive content width and height for the table of contents.',
            $hint,
        );
    }

    public function testItAddsAHintForInternalBuildStateErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::standard()),
            new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'AcroForm object ID allocation is missing.',
            ),
        );

        self::assertSame(
            'This indicates an internal document build-state mismatch; rebuild the serialization plan from the validated builder path instead of reusing partial state.',
            $hint,
        );
    }

    public function testItAddsAHintForTaggedStructureBuildErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfUa1()),
            new DocumentValidationException(
                DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID,
                'Tagged document root object id is missing.',
            ),
        );

        self::assertSame(
            'Keep tagged content, form widgets and structure parents on the validated tagged-PDF builder path so structure objects can be allocated consistently.',
            $hint,
        );
    }

    public function testItAddsAUnicodeFontHintOnlyForProfilesThatRequireUnicodeFonts(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA2u()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_UNICODE_FONTS_REQUIRED,
                'Profile PDF/A-2u requires embedded Unicode fonts. Found simple embedded font "Subset" on page 1.',
            ),
        );

        self::assertSame(
            'Use an embedded Unicode-capable font and render the affected text with embeddedFont instead of a standard PDF font.',
            $hint,
        );
    }

    public function testItDoesNotAddAUnicodeFontHintForProfilesWithoutThatRequirement(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new InvalidArgumentException('Profile PDF/A-1b requires embedded Unicode fonts. Found simple embedded font "Subset" on page 1.'),
        );

        self::assertNull($hint);
    }

    public function testItAddsATaggedPdfHintOnlyForProfilesThatRequireTaggedPdf(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA3a()),
            new DocumentValidationException(
                DocumentBuildError::TAGGED_PDF_REQUIRED,
                'Profile PDF/A-3a requires structured content in the current implementation.',
            ),
        );

        self::assertSame(
            'Use beginStructure()/endStructure() for containers and TextOptions(tag: ...) for leaf roles.',
            $hint,
        );
    }

    public function testItAddsAnImageAccessibilityHintForTaggedImageProfiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_IMAGE_ACCESSIBILITY_REQUIRED,
                'Tagged PDF profiles require accessibility metadata for image 1 on page 1.',
            ),
        );

        self::assertSame(
            'Provide ImageAccessibility for each image and either set altText or mark decorative images as decorative.',
            $hint,
        );
    }

    public function testItDoesNotAddATaggedPdfHintForNonTaggedProfiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA3b()),
            new InvalidArgumentException('Profile PDF/A-3b requires structured content in the current implementation.'),
        );

        self::assertNull($hint);
    }

    public function testItAddsAnAcroFormHintForBlockedPdfA23Profiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA2u()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED,
                'Profile PDF/A-2u does not allow AcroForm fields in the current PDF/A-2/3 scope.',
            ),
        );

        self::assertSame(
            'Remove AcroForm fields for this profile, or switch to a non-PDF/A profile. Only the explicitly enabled PDF/A form subsets are currently supported.',
            $hint,
        );
    }

    public function testItAddsAnAnnotationSubsetHintForBlockedPdfAAnnotations(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA2a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ANNOTATION_NOT_ALLOWED,
                'Profile PDF/A-2a does not support the current page annotation implementation on page 1.',
            ),
        );

        self::assertSame(
            'Use only the currently validated page-annotation subset for this profile; unsupported annotation types and tagging combinations remain blocked.',
            $hint,
        );
    }

    public function testItAddsAnAnnotationAppearanceHintWhenAppearanceStreamsAreRequired(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ANNOTATION_APPEARANCE_REQUIRED,
                'Profile PDF/A-1b does not allow the current page annotation implementation because annotation appearance streams are required on page 1.',
            ),
        );

        self::assertSame(
            'Provide appearance streams for printable annotations in this profile, or remove the affected annotation.',
            $hint,
        );
    }

    public function testItAddsAnAnnotationAltTextHintForTaggedProfiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ANNOTATION_ALT_TEXT_REQUIRED,
                'Profile PDF/A-1a requires alternative text for link annotation 1 on page 1.',
            ),
        );

        self::assertSame(
            'Set an accessible label or alternative text on the affected annotation so the tagged PDF path can serialize it accessibly.',
            $hint,
        );
    }

    public function testItAddsAFormAltTextHintForTaggedFormProfiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_FORM_ALT_TEXT_REQUIRED,
                'Profile PDF/A-1a requires an alternative description for form field "name".',
            ),
        );

        self::assertSame(
            'Set alternativeName on each affected form field, radio group and radio choice so the tagged form path remains accessible.',
            $hint,
        );
    }

    public function testItAddsAPushButtonActionHintForBlockedPdfAFormActions(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_PUSH_BUTTON_ACTION_NOT_ALLOWED,
                'Profile PDF/A-1a does not allow push button URI actions. Use an inert button without /A.',
            ),
        );

        self::assertSame(
            'Use inert push buttons without URI actions in this profile, or switch to a profile that allows the intended interaction model.',
            $hint,
        );
    }

    public function testItAddsAnAttachmentMimeTypeHintWhenAttachmentsAreAllowed(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA3b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ATTACHMENT_MIME_TYPE_REQUIRED,
                'Profile PDF/A-3b requires an embedded file MIME type for attachment 1.',
            ),
        );

        self::assertSame(
            'Set a MIME type on each EmbeddedFile so the attachment can be serialized as a valid associated file.',
            $hint,
        );
    }

    public function testItAddsATransparencyHintWhenTheProfileBlocksTransparency(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_TRANSPARENCY_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow soft-mask image transparency for image resource 1 on page 1.',
            ),
        );

        self::assertSame(
            'Remove soft masks from image resources or flatten transparency before rendering in this profile.',
            $hint,
        );
    }

    public function testItAddsAnEncryptionHintForPdfAProfiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA3b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ENCRYPTION_NOT_ALLOWED,
                'Profile PDF/A-3b does not allow encryption.',
            ),
        );

        self::assertSame(
            'Disable document encryption for PDF/A output; archival profiles require an unencrypted file.',
            $hint,
        );
    }

    public function testItAddsAnImageColorSpaceHintForPdfA1Profiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_IMAGE_COLOR_SPACE_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow custom image color space definitions in the current implementation for image resource 1 on page 1.',
            ),
        );

        self::assertSame(
            'Use the validated PDF/A-1 image path without custom image color space definitions, or move to a profile that supports the intended color handling.',
            $hint,
        );
    }

    public function testItAddsAPdfA4MetadataHintForPdfA4ObjectGraphFailures(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA4()),
            new DocumentValidationException(
                DocumentBuildError::PDFA4_METADATA_INVALID,
                'Profile PDF/A-4 metadata stream must serialize <pdfaid:rev>2020</pdfaid:rev>.',
            ),
        );

        self::assertSame(
            'Keep PDF/A-4 metadata on the dedicated PDF 2.0 path: write pdfaid:part=4 and pdfaid:rev=2020, omit Info/OutputIntents, and only write pdfaid:conformance for 4e/4f.',
            $hint,
        );
    }

    public function testItAddsAnOutputIntentHintForPdfAColorFailures(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID,
                'PDF/A output intent "Press Condition" is not plausible for an RGB ICC profile.',
            ),
        );

        self::assertSame(
            'Use the default PDF/A output intent or pass ->pdfaOutputIntent(...) with a readable ICC profile that matches the document color usage.',
            $hint,
        );
    }

    public function testItAddsALowLevelPdfAHintForCodedLowLevelValidationErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_LOW_LEVEL_CONTENT_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow low-level PDF operator "gs" in page content stream on page 1.',
            ),
        );

        self::assertSame(
            'Use the high-level document APIs instead of raw PDF dictionary or content stream injections for this profile.',
            $hint,
        );
    }

    public function testItAddsAnObjectGraphHintForCodedPdfAObjectGraphErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID,
                'PDF/A-1 tagged catalog requires a StructTreeRoot object ID.',
            ),
        );

        self::assertSame(
            'Keep the generated PDF/A object graph on the validated serializer path; avoid custom low-level object wiring that bypasses the builder and validator invariants.',
            $hint,
        );
    }

    public function testItAddsATaggedStructureHintForCodedTaggedStructureErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'PDF/A tagged StructTreeRoot must reference ParentTree 9 0 R.',
            ),
        );

        self::assertSame(
            'Use beginStructure()/endStructure() consistently and keep the tagged reading order, ParentTree, MCIDs and StructElem hierarchy on the validated tagged PDF path.',
            $hint,
        );
    }

    public function testItAddsAMetadataConsistencyHintForCodedMetadataErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b(), title: 'Archive Copy'),
            new DocumentValidationException(
                DocumentBuildError::PDFA_METADATA_INCONSISTENT,
                'Profile PDF/A-1b requires consistent Info/XMP metadata for Title.',
            ),
        );

        self::assertSame(
            'Keep Info and XMP metadata synchronized for this profile; update title, author, subject, language and timestamps through the document builder instead of low-level metadata overrides.',
            $hint,
        );
    }

    public function testItAddsAnActionHintForCodedPdfAActionErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_ACTION_NOT_ALLOWED,
                'Profile PDF/A-1b does not allow remote outline actions such as GoToR in outline 1.',
            ),
        );

        self::assertSame(
            'Use only PDF/A-safe navigation: internal destinations instead of remote or URI actions, and avoid action dictionaries that the active profile forbids.',
            $hint,
        );
    }

    public function testItAddsAProfileSelectionHintForBlockedPdfAProfiles(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA4f()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_PROFILE_NOT_SUPPORTED,
                'Profile PDF/A-4f is blocked until the dedicated PDF/A-4f attachment and PDF 2.0 validation path are implemented.',
            ),
        );

        self::assertSame(
            'Use only PDF/A profiles that are explicitly enabled in the current implementation scope, or switch to a non-PDF/A profile until the blocked profile family is implemented.',
            $hint,
        );
    }

    public function testItAddsATaggedStructureHintForSupportedStructureGuardErrors(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1a()),
            new DocumentValidationException(
                DocumentBuildError::PDFA_TAGGED_STRUCTURE_INVALID,
                'Profile PDF/A-1a does not allow empty tagged lists. Tagged list 1 has no items.',
            ),
        );

        self::assertSame(
            'Use beginStructure()/endStructure() consistently and keep the tagged reading order, ParentTree, MCIDs and StructElem hierarchy on the validated tagged PDF path.',
            $hint,
        );
    }

    public function testItReturnsNullForLegacyInvalidArgumentExceptions(): void
    {
        $hint = $this->resolver->resolve(
            new Document(profile: Profile::pdfA1b()),
            new InvalidArgumentException('Profile PDF/A-1b requires embedded fonts. Found standard font "Helvetica" on page 1.'),
        );

        self::assertNull($hint);
    }
}
