<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class Profile
{
    private function __construct(
        private ProfileFamily $family,
        private float $version,
        private ?int $part = null,
        private ?string $conformance = null,
    ) {
    }

    public static function standard(float $version = Version::V1_4): self
    {
        self::assertSupportedStandardVersion($version);

        return new self(ProfileFamily::STANDARD, $version);
    }

    public static function pdf10(): self
    {
        return self::standard(Version::V1_0);
    }

    public static function pdf11(): self
    {
        return self::standard(Version::V1_1);
    }

    public static function pdf12(): self
    {
        return self::standard(Version::V1_2);
    }

    public static function pdf13(): self
    {
        return self::standard(Version::V1_3);
    }

    public static function pdf14(): self
    {
        return self::standard(Version::V1_4);
    }

    public static function pdf15(): self
    {
        return self::standard(Version::V1_5);
    }

    public static function pdf16(): self
    {
        return self::standard(Version::V1_6);
    }

    public static function pdf17(): self
    {
        return self::standard(Version::V1_7);
    }

    public static function pdf20(): self
    {
        return self::standard(Version::V2_0);
    }

    public static function pdfA1a(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_4, 1, 'A');
    }

    public static function pdfA1b(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_4, 1, 'B');
    }

    public static function pdfA2a(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_7, 2, 'A');
    }

    public static function pdfA2b(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_7, 2, 'B');
    }

    public static function pdfA2u(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_7, 2, 'U');
    }

    public static function pdfA3a(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_7, 3, 'A');
    }

    public static function pdfA3b(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_7, 3, 'B');
    }

    public static function pdfA3u(): self
    {
        return new self(ProfileFamily::PDFA, Version::V1_7, 3, 'U');
    }

    public static function pdfA4(): self
    {
        return new self(ProfileFamily::PDFA, Version::V2_0, 4);
    }

    public static function pdfA4e(): self
    {
        return new self(ProfileFamily::PDFA, Version::V2_0, 4, 'E');
    }

    public static function pdfA4f(): self
    {
        return new self(ProfileFamily::PDFA, Version::V2_0, 4, 'F');
    }

    public static function pdfUa1(): self
    {
        return new self(ProfileFamily::PDFUA, Version::V1_7, 1);
    }

    public static function pdfUa2(): self
    {
        return new self(ProfileFamily::PDFUA, Version::V2_0, 2);
    }

    public function family(): ProfileFamily
    {
        return $this->family;
    }

    public function version(): float
    {
        return $this->version;
    }

    public function part(): ?int
    {
        return $this->part;
    }

    public function conformance(): ?string
    {
        return $this->conformance;
    }

    public function name(): string
    {
        return match ($this->family) {
            ProfileFamily::STANDARD => 'standard',
            ProfileFamily::PDFA => $this->buildPdfAName(),
            ProfileFamily::PDFUA => $this->buildPdfUaName(),
            default => $this->family->value,
        };
    }

    public function isStandard(): bool
    {
        return $this->family === ProfileFamily::STANDARD;
    }

    public function isPdfA(): bool
    {
        return $this->family === ProfileFamily::PDFA;
    }

    public function isPdfA1(): bool
    {
        return $this->family === ProfileFamily::PDFA
            && $this->part === 1;
    }

    public function isPdfA2(): bool
    {
        return $this->family === ProfileFamily::PDFA
            && $this->part === 2;
    }

    public function isPdfA2u(): bool
    {
        return $this->family === ProfileFamily::PDFA
            && $this->part === 2
            && $this->conformance === 'U';
    }

    public function isPdfA3(): bool
    {
        return $this->family === ProfileFamily::PDFA
            && $this->part === 3;
    }

    public function isPdfA4(): bool
    {
        return $this->family === ProfileFamily::PDFA
            && $this->part === 4;
    }

    public function isPdfA4f(): bool
    {
        return $this->family === ProfileFamily::PDFA
            && $this->part === 4
            && $this->conformance === 'F';
    }

    public function isPdfUa(): bool
    {
        return $this->family === ProfileFamily::PDFUA;
    }

    public function isPdfUa1(): bool
    {
        return $this->family === ProfileFamily::PDFUA
            && $this->part === 1;
    }

    public function isPdfUa2(): bool
    {
        return $this->family === ProfileFamily::PDFUA
            && $this->part === 2;
    }

    public function pdfaPart(): ?int
    {
        if (!$this->isPdfA()) {
            return null;
        }

        return $this->part;
    }

    public function pdfaConformance(): ?string
    {
        if (!$this->isPdfA()) {
            return null;
        }

        return $this->conformance;
    }

    public function pdfaSupport(): ?PdfAProfileSupport
    {
        return PdfAProfileSupport::for($this);
    }

    public function supportsCurrentPdfAImplementation(): bool
    {
        $support = $this->pdfaSupport();

        if ($support === null) {
            return true;
        }

        return $support->isSupported;
    }

    public function pdfuaPart(): ?int
    {
        if (!$this->isPdfUa()) {
            return null;
        }

        return $this->part;
    }

    public function defaultsAttachmentRelationshipToData(): bool
    {
        return $this->defaultsDocumentAttachmentRelationshipToData();
    }

    public function defaultsDocumentAttachmentRelationshipToData(): bool
    {
        return $this->isPdfA3()
            || $this->isPdfA4f();
    }

    public function displaysDocumentTitleInViewer(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresAnnotationAppearanceStreams(): bool
    {
        return $this->isPdfA();
    }

    public function requiresArtifactGraphicElements(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresDocumentLanguage(): bool
    {
        return $this->isPdfUa()
            || $this->pdfaCapabilityRequired(PdfACapability::DOCUMENT_LANGUAGE);
    }

    public function requiresDocumentStructure(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresDocumentTitle(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresEmbeddedUnicodeFonts(): bool
    {
        return $this->requiresExtractableEmbeddedUnicodeFonts();
    }

    public function requiresExtractableEmbeddedUnicodeFonts(): bool
    {
        return $this->isPdfUa()
            || $this->pdfaCapabilityRequired(PdfACapability::EXTRACTABLE_UNICODE_FONTS);
    }

    public function requiresFigureAltText(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresFormFieldAlternativeDescriptions(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresLinkAnnotationAlternativeDescriptions(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresPageAnnotationAlternativeDescriptions(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresPageAnnotationTabOrder(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresPrintableAnnotations(): bool
    {
        return $this->isPdfA();
    }

    public function requiresTaggedFormFields(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresTaggedImages(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresTaggedLinkAnnotations(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresTaggedPageAnnotations(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA() && $this->conformance === 'A');
    }

    public function requiresTaggedPdf(): bool
    {
        return $this->isPdfUa()
            || $this->pdfaCapabilityRequired(PdfACapability::TAGGED_PDF);
    }

    public function supportsAcroForms(): bool
    {
        return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
            || (!$this->isPdfA()
            && !$this->isPdfUa());
    }

    public function supportsAes128Encryption(): bool
    {
        return $this->version >= Version::V1_6 && $this->supportsEncryption();
    }

    public function supportsAes256Encryption(): bool
    {
        return $this->version >= Version::V1_7 && $this->supportsEncryption();
    }

    public function supportsAssociatedFiles(): bool
    {
        return $this->supportsDocumentAssociatedFiles();
    }

    public function supportsDocumentAssociatedFiles(): bool
    {
        return ($this->isStandard() && $this->version >= Version::V2_0)
            || $this->pdfaCapabilityAllowed(PdfACapability::DOCUMENT_ASSOCIATED_FILES);
    }

    public function supportsCurrentCheckboxImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
                || $this->requiresTaggedFormFields();
        }

        return $this->supportsAcroForms()
            || $this->requiresTaggedFormFields();
    }

    public function supportsCurrentComboBoxImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
                || $this->requiresTaggedFormFields();
        }

        return $this->supportsAcroForms()
            || $this->requiresTaggedFormFields();
    }

    public function supportsCurrentListBoxImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
                || $this->requiresTaggedFormFields();
        }

        return $this->supportsAcroForms()
            || $this->requiresTaggedFormFields();
    }

    public function supportsCurrentOptionalContentGroupImplementation(): bool
    {
        return !$this->isPdfA();
    }

    public function supportsCurrentPageAnnotationsImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::LINK_ANNOTATIONS)
                || $this->pdfaCapabilityAllowed(PdfACapability::NON_LINK_PAGE_ANNOTATIONS);
        }

        return !$this->isPdfUa();
    }

    public function supportsCurrentPushButtonImplementation(): bool
    {
        if ($this->isPdfA2() || $this->isPdfA3() || $this->isPdfA4() || ($this->isPdfA() && $this->conformance === 'A')) {
            return false;
        }

        return ($this->supportsAcroForms() && !($this->isPdfA1() && $this->conformance === 'A'))
            || $this->isPdfUa();
    }

    public function supportsCurrentRadioButtonImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
                || $this->requiresTaggedFormFields();
        }

        return $this->supportsAcroForms()
            || $this->requiresTaggedFormFields();
    }

    public function supportsCurrentSignatureFieldImplementation(): bool
    {
        if ($this->isPdfA2() || $this->isPdfA3() || $this->isPdfA4() || ($this->isPdfA() && $this->conformance === 'A')) {
            return false;
        }

        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
                || $this->requiresTaggedFormFields();
        }

        return $this->supportsAcroForms()
            || $this->requiresTaggedFormFields();
    }

    public function supportsCurrentTextFieldImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::ACRO_FORM_FIELDS)
                || $this->requiresTaggedFormFields();
        }

        return $this->supportsAcroForms()
            || $this->requiresTaggedFormFields();
    }

    public function supportsCurrentTransparencyImplementation(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::TRANSPARENCY);
        }

        return true;
    }

    public function supportsEmbeddedFileAttachments(): bool
    {
        return $this->supportsDocumentEmbeddedFileAttachments();
    }

    public function supportsDocumentEmbeddedFileAttachments(): bool
    {
        return $this->isStandard()
            || $this->isPdfUa()
            || $this->pdfaCapabilityAllowed(PdfACapability::DOCUMENT_EMBEDDED_ATTACHMENTS);
    }

    public function supportsEncryption(): bool
    {
        return !$this->isPdfA();
    }

    public function supportsOptionalContentGroups(): bool
    {
        return $this->version >= Version::V1_5 && !$this->isPdfA();
    }

    public function supportsRc440Encryption(): bool
    {
        return $this->version >= Version::V1_3 && $this->supportsEncryption();
    }

    public function supportsStructure(): bool
    {
        return $this->version >= Version::V1_4;
    }

    public function supportsTransparency(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityAllowed(PdfACapability::TRANSPARENCY);
        }

        return $this->version >= Version::V1_4;
    }

    public function supportsWinAnsiEncoding(): bool
    {
        return $this->version > Version::V1_0;
    }

    public function supportsXmpMetadata(): bool
    {
        return $this->version >= Version::V1_4;
    }

    public function usesPdfAOutputIntent(): bool
    {
        return $this->pdfaCapabilityRequired(PdfACapability::OUTPUT_INTENT);
    }

    public function writesInfoDictionary(): bool
    {
        if ($this->isPdfA()) {
            return $this->pdfaCapabilityRequired(PdfACapability::INFO_DICTIONARY);
        }

        return true;
    }

    public function writesPdfAIdentificationMetadata(): bool
    {
        return $this->isPdfA();
    }

    public function writesPdfARevisionMetadata(): bool
    {
        return $this->pdfaPart() === 4;
    }

    public function writesPdfUaIdentificationMetadata(): bool
    {
        return $this->isPdfUa();
    }

    private function pdfaCapabilityAllowed(PdfACapability $capability): bool
    {
        if (!$this->isPdfA()) {
            return false;
        }

        return $this->pdfaSupport()?->capabilityRule($capability)->allowed ?? false;
    }

    private function pdfaCapabilityRequired(PdfACapability $capability): bool
    {
        if (!$this->isPdfA()) {
            return false;
        }

        return $this->pdfaSupport()?->capabilityRule($capability)->required ?? false;
    }

    private function buildPdfAName(): string
    {
        if ($this->part === null) {
            throw new InvalidArgumentException('PDF/A profiles require a part.');
        }

        return 'PDF/A-' . $this->part . strtolower((string) $this->conformance);
    }

    private function buildPdfUaName(): string
    {
        if ($this->part === null) {
            throw new InvalidArgumentException('PDF/UA profiles require a part.');
        }

        return 'PDF/UA-' . $this->part;
    }

    private static function assertSupportedStandardVersion(float $version): void
    {
        if (!in_array($version, Version::all(), true)) {
            throw new InvalidArgumentException('Unsupported PDF version. Supported versions are 1.0 to 1.7 and 2.0.');
        }
    }
}
