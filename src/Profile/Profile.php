<?php

declare(strict_types=1);

namespace Kalle\Pdf\Profile;

use InvalidArgumentException;

final readonly class Profile
{
    private function __construct(
        private string $name,
        private float $version,
    ) {
    }

    public static function standard(float $version = PdfVersion::V1_4): self
    {
        self::assertSupportedStandardVersion($version);

        return new self('standard', $version);
    }

    public static function pdf10(): self
    {
        return self::standard(PdfVersion::V1_0);
    }

    public static function pdf11(): self
    {
        return self::standard(PdfVersion::V1_1);
    }

    public static function pdf12(): self
    {
        return self::standard(PdfVersion::V1_2);
    }

    public static function pdf13(): self
    {
        return self::standard(PdfVersion::V1_3);
    }

    public static function pdf14(): self
    {
        return self::standard(PdfVersion::V1_4);
    }

    public static function pdf15(): self
    {
        return self::standard(PdfVersion::V1_5);
    }

    public static function pdf16(): self
    {
        return self::standard(PdfVersion::V1_6);
    }

    public static function pdf17(): self
    {
        return self::standard(PdfVersion::V1_7);
    }

    public static function pdf20(): self
    {
        return self::standard(PdfVersion::V2_0);
    }

    public static function pdfA1a(): self
    {
        return new self('PDF/A-1a', PdfVersion::V1_4); // PDF/A-1 is based on PDF 1.4.
    }

    public static function pdfA1b(): self
    {
        return new self('PDF/A-1b', PdfVersion::V1_4); // PDF/A-1 is based on PDF 1.4.
    }

    public static function pdfA2a(): self
    {
        return new self('PDF/A-2a', PdfVersion::V1_7); // PDF/A-2 is based on PDF 1.7.
    }

    public static function pdfA2b(): self
    {
        return new self('PDF/A-2b', PdfVersion::V1_7); // PDF/A-2 is based on PDF 1.7.
    }

    public static function pdfA2u(): self
    {
        return new self('PDF/A-2u', PdfVersion::V1_7); // PDF/A-2 is based on PDF 1.7.
    }

    public static function pdfA3a(): self
    {
        return new self('PDF/A-3a', PdfVersion::V1_7); // PDF/A-3 is based on PDF 1.7.
    }

    public static function pdfA3b(): self
    {
        return new self('PDF/A-3b', PdfVersion::V1_7); // PDF/A-3 is based on PDF 1.7.
    }

    public static function pdfA3u(): self
    {
        return new self('PDF/A-3u', PdfVersion::V1_7); // PDF/A-3 is based on PDF 1.7.
    }

    public static function pdfA4(): self
    {
        return new self('PDF/A-4', PdfVersion::V2_0); // PDF/A-4 is based on PDF 2.0.
    }

    public static function pdfA4e(): self
    {
        return new self('PDF/A-4e', PdfVersion::V2_0); // PDF/A-4e is based on PDF 2.0.
    }

    public static function pdfA4f(): self
    {
        return new self('PDF/A-4f', PdfVersion::V2_0); // PDF/A-4f is based on PDF 2.0.
    }

    public static function pdfUa1(): self
    {
        return new self('PDF/UA-1', PdfVersion::V1_7); // PDF/UA-1 is based on PDF 1.7.
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): float
    {
        return $this->version;
    }

    public function isStandard(): bool
    {
        return $this->name === 'standard';
    }

    public function isPdfA(): bool
    {
        return str_starts_with($this->name, 'PDF/A-');
    }

    public function isPdfUa(): bool
    {
        return str_starts_with($this->name, 'PDF/UA-');
    }

    public function isPdfUa1(): bool
    {
        return $this->name === 'PDF/UA-1';
    }

    public function isPdfA2(): bool
    {
        return $this->pdfaPart() === 2;
    }

    public function isPdfA1(): bool
    {
        return $this->pdfaPart() === 1;
    }

    public function isPdfA3(): bool
    {
        return $this->pdfaPart() === 3;
    }

    public function isPdfA4(): bool
    {
        return $this->pdfaPart() === 4;
    }

    public function isPdfA4f(): bool
    {
        return $this->name === 'PDF/A-4f';
    }

    public function requiresTaggedPdf(): bool
    {
        return $this->pdfaConformance() === 'A'
            || $this->isPdfUa();
    }

    public function usesPdfAOutputIntent(): bool
    {
        return in_array($this->pdfaPart(), [1, 2, 3, 4], true);
    }

    public function isPdfA2u(): bool
    {
        return $this->name === 'PDF/A-2u';
    }

    public function supportsXmpMetadata(): bool
    {
        return $this->version >= PdfVersion::V1_4;
    }

    public function supportsStructure(): bool
    {
        return $this->version >= PdfVersion::V1_4;
    }

    public function supportsTransparency(): bool
    {
        return $this->version >= PdfVersion::V1_4 && !$this->isPdfA1();
    }

    public function supportsCurrentTransparencyImplementation(): bool
    {
        return !$this->isPdfA1();
    }

    public function supportsCurrentOptionalContentGroupImplementation(): bool
    {
        return !$this->isPdfA();
    }

    public function supportsEncryption(): bool
    {
        return !$this->isPdfA();
    }

    public function supportsRc440Encryption(): bool
    {
        return $this->version >= PdfVersion::V1_3 && $this->supportsEncryption();
    }

    public function supportsAes128Encryption(): bool
    {
        return $this->version >= PdfVersion::V1_6 && $this->supportsEncryption();
    }

    public function supportsAes256Encryption(): bool
    {
        return $this->version >= PdfVersion::V1_7 && $this->supportsEncryption();
    }

    public function supportsAssociatedFiles(): bool
    {
        return ($this->isStandard() && $this->version >= PdfVersion::V2_0)
            || $this->isPdfA3()
            || $this->isPdfA4f();
    }

    public function supportsEmbeddedFileAttachments(): bool
    {
        return $this->isStandard()
            || $this->isPdfUa()
            || $this->isPdfA3()
            || $this->isPdfA4f();
    }

    public function defaultsAttachmentRelationshipToData(): bool
    {
        return $this->isPdfA3()
            || $this->isPdfA4f();
    }

    public function requiresEmbeddedUnicodeFonts(): bool
    {
        return $this->isPdfA()
            || $this->isPdfUa();
    }

    public function supportsAcroForms(): bool
    {
        return !$this->isPdfA()
            && !$this->isPdfUa();
    }

    public function supportsCurrentTextFieldImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentCheckboxImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentPushButtonImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentRadioButtonImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentComboBoxImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentListBoxImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentSignatureFieldImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function writesInfoDictionary(): bool
    {
        return !$this->isPdfA4();
    }

    public function writesPdfAIdentificationMetadata(): bool
    {
        return $this->isPdfA();
    }

    public function writesPdfUaIdentificationMetadata(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresDocumentLanguage(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresDocumentTitle(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresDocumentStructure(): bool
    {
        return $this->isPdfUa();
    }

    public function displaysDocumentTitleInViewer(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresTaggedImages(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresFigureAltText(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresArtifactGraphicElements(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresTaggedLinkAnnotations(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresLinkAnnotationAlternativeDescriptions(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresTaggedPageAnnotations(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresPageAnnotationAlternativeDescriptions(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresTaggedFormFields(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresFormFieldAlternativeDescriptions(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresPageAnnotationTabOrder(): bool
    {
        return $this->isPdfUa();
    }

    public function supportsCurrentPageAnnotationsImplementation(): bool
    {
        return !$this->isPdfUa();
    }

    public function requiresPrintableAnnotations(): bool
    {
        return $this->isPdfA();
    }

    public function requiresAnnotationAppearanceStreams(): bool
    {
        return $this->isPdfA();
    }

    public function supportsWinAnsiEncoding(): bool
    {
        return $this->version > PdfVersion::V1_0;
    }

    public function supportsOptionalContentGroups(): bool
    {
        return $this->version >= PdfVersion::V1_5 && !$this->isPdfA();
    }

    public function pdfaPart(): ?int
    {
        return match ($this->name) {
            'PDF/A-1a', 'PDF/A-1b' => 1,
            'PDF/A-2a', 'PDF/A-2b', 'PDF/A-2u' => 2,
            'PDF/A-3a', 'PDF/A-3b', 'PDF/A-3u' => 3,
            'PDF/A-4', 'PDF/A-4e', 'PDF/A-4f' => 4,
            default => null,
        };
    }

    public function pdfaConformance(): ?string
    {
        return match ($this->name) {
            'PDF/A-1a', 'PDF/A-2a', 'PDF/A-3a' => 'A',
            'PDF/A-1b', 'PDF/A-2b', 'PDF/A-3b' => 'B',
            'PDF/A-2u', 'PDF/A-3u' => 'U',
            'PDF/A-4e' => 'E',
            'PDF/A-4f' => 'F',
            'PDF/A-4' => null,
            default => null,
        };
    }

    public function pdfuaPart(): ?int
    {
        return match ($this->name) {
            'PDF/UA-1' => 1,
            default => null,
        };
    }

    private static function assertSupportedStandardVersion(float $version): void
    {
        $supportedVersions = PdfVersion::all();

        if (!in_array($version, $supportedVersions, true)) {
            throw new InvalidArgumentException('Unsupported PDF version. Supported versions are 1.0 to 1.7 and 2.0.');
        }
    }
}
