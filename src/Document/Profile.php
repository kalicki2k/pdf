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
            || ($this->isPdfA1() && $this->conformance === 'A');
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
            || ($this->isPdfA() && ($this->conformance === 'A' || $this->conformance === 'U'));
    }

    public function requiresFigureAltText(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA1() && $this->conformance === 'A');
    }

    public function requiresFormFieldAlternativeDescriptions(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresLinkAnnotationAlternativeDescriptions(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA1() && $this->conformance === 'A');
    }

    public function requiresPageAnnotationAlternativeDescriptions(): bool
    {
        return $this->isPdfUa();
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
        return $this->isPdfUa();
    }

    public function requiresTaggedImages(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA1() && $this->conformance === 'A');
    }

    public function requiresTaggedLinkAnnotations(): bool
    {
        return $this->isPdfUa()
            || ($this->isPdfA1() && $this->conformance === 'A');
    }

    public function requiresTaggedPageAnnotations(): bool
    {
        return $this->isPdfUa();
    }

    public function requiresTaggedPdf(): bool
    {
        return $this->pdfaConformance() === 'A'
            || $this->isPdfUa();
    }

    public function supportsAcroForms(): bool
    {
        return !$this->isPdfA()
            && !$this->isPdfUa();
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
            || $this->isPdfA3()
            || $this->isPdfA4f();
    }

    public function supportsCurrentCheckboxImplementation(): bool
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

    public function supportsCurrentOptionalContentGroupImplementation(): bool
    {
        return !$this->isPdfA();
    }

    public function supportsCurrentPageAnnotationsImplementation(): bool
    {
        return !$this->isPdfUa();
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

    public function supportsCurrentSignatureFieldImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentTextFieldImplementation(): bool
    {
        return $this->supportsAcroForms()
            || $this->isPdfUa();
    }

    public function supportsCurrentTransparencyImplementation(): bool
    {
        return !$this->isPdfA1();
    }

    public function supportsEmbeddedFileAttachments(): bool
    {
        return $this->supportsDocumentEmbeddedFileAttachments();
    }

    public function supportsDocumentEmbeddedFileAttachments(): bool
    {
        return $this->isStandard()
            || $this->isPdfUa()
            || $this->isPdfA3()
            || $this->isPdfA4f();
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
        return $this->version >= Version::V1_4 && !$this->isPdfA1();
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
        return $this->family === ProfileFamily::PDFA;
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
