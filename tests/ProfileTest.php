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
    public function it_exposes_the_base_version_for_pdf_ua_1(): void
    {
        self::assertSame('PDF/UA-1', Profile::pdfUa1()->name());
        self::assertSame(1.7, Profile::pdfUa1()->version());
        self::assertTrue(Profile::pdfUa1()->isPdfUa());
        self::assertTrue(Profile::pdfUa1()->isPdfUa1());
    }

    #[Test]
    public function it_detects_profiles_that_require_tagged_pdf(): void
    {
        self::assertTrue(Profile::pdfA2a()->requiresTaggedPdf());
        self::assertTrue(Profile::pdfA3a()->requiresTaggedPdf());
        self::assertTrue(Profile::pdfUa1()->requiresTaggedPdf());
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
    public function it_detects_profiles_that_support_the_current_transparency_implementation(): void
    {
        self::assertTrue(Profile::pdf14()->supportsCurrentTransparencyImplementation());
        self::assertTrue(Profile::pdfA2u()->supportsCurrentTransparencyImplementation());
        self::assertFalse(Profile::pdfA1b()->supportsCurrentTransparencyImplementation());
    }

    #[Test]
    public function it_detects_profiles_that_support_the_current_optional_content_group_implementation(): void
    {
        self::assertTrue(Profile::pdf15()->supportsCurrentOptionalContentGroupImplementation());
        self::assertTrue(Profile::pdf14()->supportsCurrentOptionalContentGroupImplementation());
        self::assertFalse(Profile::pdfA2u()->supportsCurrentOptionalContentGroupImplementation());
    }

    #[Test]
    public function it_detects_profiles_that_support_aes_128_encryption(): void
    {
        self::assertFalse(Profile::pdf15()->supportsAes128Encryption());
        self::assertTrue(Profile::pdf16()->supportsAes128Encryption());
        self::assertFalse(Profile::pdfA2u()->supportsAes128Encryption());
    }

    #[Test]
    public function it_detects_profiles_that_support_encryption(): void
    {
        self::assertTrue(Profile::pdf14()->supportsEncryption());
        self::assertTrue(Profile::pdf20()->supportsEncryption());
        self::assertFalse(Profile::pdfA2u()->supportsEncryption());
        self::assertFalse(Profile::pdfA4f()->supportsEncryption());
    }

    #[Test]
    public function it_detects_profiles_that_support_aes_256_encryption(): void
    {
        self::assertFalse(Profile::pdf16()->supportsAes256Encryption());
        self::assertTrue(Profile::pdf17()->supportsAes256Encryption());
        self::assertFalse(Profile::pdfA3b()->supportsAes256Encryption());
    }

    #[Test]
    public function it_detects_profiles_that_support_associated_files(): void
    {
        self::assertFalse(Profile::pdf17()->supportsAssociatedFiles());
        self::assertTrue(Profile::pdf20()->supportsAssociatedFiles());
        self::assertTrue(Profile::pdfA3b()->supportsAssociatedFiles());
        self::assertTrue(Profile::pdfA4f()->supportsAssociatedFiles());
        self::assertFalse(Profile::pdfA4()->supportsAssociatedFiles());
    }

    #[Test]
    public function it_detects_profiles_that_support_embedded_file_attachments(): void
    {
        self::assertTrue(Profile::pdf14()->supportsEmbeddedFileAttachments());
        self::assertFalse(Profile::pdfA2u()->supportsEmbeddedFileAttachments());
        self::assertTrue(Profile::pdfA3b()->supportsEmbeddedFileAttachments());
        self::assertTrue(Profile::pdfA4f()->supportsEmbeddedFileAttachments());
    }

    #[Test]
    public function it_detects_profiles_that_default_attachment_relationships_to_data(): void
    {
        self::assertFalse(Profile::pdf20()->defaultsAttachmentRelationshipToData());
        self::assertFalse(Profile::pdfA2u()->defaultsAttachmentRelationshipToData());
        self::assertTrue(Profile::pdfA3b()->defaultsAttachmentRelationshipToData());
        self::assertTrue(Profile::pdfA4f()->defaultsAttachmentRelationshipToData());
    }

    #[Test]
    public function it_detects_profiles_that_require_embedded_unicode_fonts(): void
    {
        self::assertFalse(Profile::pdf14()->requiresEmbeddedUnicodeFonts());
        self::assertTrue(Profile::pdfA2u()->requiresEmbeddedUnicodeFonts());
        self::assertTrue(Profile::pdfA3b()->requiresEmbeddedUnicodeFonts());
    }

    #[Test]
    public function it_detects_profiles_that_support_acro_forms(): void
    {
        self::assertTrue(Profile::pdf14()->supportsAcroForms());
        self::assertTrue(Profile::pdf20()->supportsAcroForms());
        self::assertFalse(Profile::pdfUa1()->supportsAcroForms());
        self::assertFalse(Profile::pdfA2u()->supportsAcroForms());
        self::assertFalse(Profile::pdfA4f()->supportsAcroForms());
    }

    #[Test]
    public function it_detects_profiles_that_write_the_info_dictionary(): void
    {
        self::assertTrue(Profile::pdf14()->writesInfoDictionary());
        self::assertTrue(Profile::pdfA2u()->writesInfoDictionary());
        self::assertFalse(Profile::pdfA4()->writesInfoDictionary());
        self::assertFalse(Profile::pdfA4f()->writesInfoDictionary());
    }

    #[Test]
    public function it_detects_profiles_that_write_pdf_a_identification_metadata(): void
    {
        self::assertFalse(Profile::pdf14()->writesPdfAIdentificationMetadata());
        self::assertTrue(Profile::pdfA2u()->writesPdfAIdentificationMetadata());
        self::assertTrue(Profile::pdfA4()->writesPdfAIdentificationMetadata());
    }

    #[Test]
    public function it_detects_profiles_that_write_pdf_ua_identification_metadata(): void
    {
        self::assertFalse(Profile::pdf17()->writesPdfUaIdentificationMetadata());
        self::assertTrue(Profile::pdfUa1()->writesPdfUaIdentificationMetadata());
        self::assertSame(1, Profile::pdfUa1()->pdfuaPart());
    }

    #[Test]
    public function it_detects_profiles_that_require_pdf_ua_document_metadata_and_structure(): void
    {
        self::assertFalse(Profile::pdf17()->requiresDocumentTitle());
        self::assertFalse(Profile::pdf17()->requiresDocumentLanguage());
        self::assertFalse(Profile::pdf17()->requiresDocumentStructure());
        self::assertFalse(Profile::pdf17()->displaysDocumentTitleInViewer());
        self::assertTrue(Profile::pdfUa1()->requiresDocumentTitle());
        self::assertTrue(Profile::pdfUa1()->requiresDocumentLanguage());
        self::assertTrue(Profile::pdfUa1()->requiresDocumentStructure());
        self::assertTrue(Profile::pdfUa1()->displaysDocumentTitleInViewer());
    }

    #[Test]
    public function it_detects_profiles_that_require_accessible_images(): void
    {
        self::assertFalse(Profile::pdf17()->requiresTaggedImages());
        self::assertFalse(Profile::pdf17()->requiresFigureAltText());
        self::assertTrue(Profile::pdfUa1()->requiresTaggedImages());
        self::assertTrue(Profile::pdfUa1()->requiresFigureAltText());
    }

    #[Test]
    public function it_detects_profiles_that_require_tagged_link_annotations(): void
    {
        self::assertFalse(Profile::pdf17()->requiresTaggedLinkAnnotations());
        self::assertFalse(Profile::pdfA2u()->requiresTaggedLinkAnnotations());
        self::assertTrue(Profile::pdfUa1()->requiresTaggedLinkAnnotations());
    }

    #[Test]
    public function it_detects_profiles_that_require_link_annotation_alternative_descriptions(): void
    {
        self::assertFalse(Profile::pdf17()->requiresLinkAnnotationAlternativeDescriptions());
        self::assertFalse(Profile::pdfA2u()->requiresLinkAnnotationAlternativeDescriptions());
        self::assertTrue(Profile::pdfUa1()->requiresLinkAnnotationAlternativeDescriptions());
    }

    #[Test]
    public function it_detects_profiles_that_support_the_current_page_annotation_implementation(): void
    {
        self::assertTrue(Profile::pdf17()->supportsCurrentPageAnnotationsImplementation());
        self::assertTrue(Profile::pdfA2u()->supportsCurrentPageAnnotationsImplementation());
        self::assertFalse(Profile::pdfUa1()->supportsCurrentPageAnnotationsImplementation());
    }

    #[Test]
    public function it_detects_profiles_that_require_printable_annotations(): void
    {
        self::assertFalse(Profile::pdf14()->requiresPrintableAnnotations());
        self::assertTrue(Profile::pdfA2u()->requiresPrintableAnnotations());
        self::assertTrue(Profile::pdfA4()->requiresPrintableAnnotations());
    }

    #[Test]
    public function it_detects_profiles_that_require_annotation_appearance_streams(): void
    {
        self::assertFalse(Profile::pdf14()->requiresAnnotationAppearanceStreams());
        self::assertTrue(Profile::pdfA2u()->requiresAnnotationAppearanceStreams());
        self::assertTrue(Profile::pdfA4()->requiresAnnotationAppearanceStreams());
    }

    #[Test]
    public function it_detects_profiles_that_support_win_ansi_encoding(): void
    {
        self::assertFalse(Profile::pdf10()->supportsWinAnsiEncoding());
        self::assertTrue(Profile::pdf11()->supportsWinAnsiEncoding());
    }

    #[Test]
    public function it_detects_profiles_that_support_rc4_40_encryption(): void
    {
        self::assertFalse(Profile::pdf12()->supportsRc440Encryption());
        self::assertTrue(Profile::pdf13()->supportsRc440Encryption());
        self::assertFalse(Profile::pdfA3b()->supportsRc440Encryption());
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
