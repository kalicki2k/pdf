<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Preparation;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Font\StandardFontName;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\PdfVersion;

/**
 * @internal Guards document features against profile and version requirements.
 */
class DocumentProfileGuard
{
    public function __construct(private Document $document)
    {
    }

    public function assertAllowsEncryptionAlgorithm(EncryptionAlgorithm $algorithm): void
    {
        $this->assertAllowsEncryption();

        if ($algorithm === EncryptionAlgorithm::RC4_40 && !$this->document->getProfile()->supportsRc440Encryption()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow RC4 40-bit encryption. PDF 1.3 or higher is required.',
                PdfVersion::format($this->document->getVersion()),
            ));
        }

        if ($algorithm === EncryptionAlgorithm::AES_128 && !$this->document->getProfile()->supportsAes128Encryption()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow AES-128 encryption. PDF 1.6 or higher is required.',
                PdfVersion::format($this->document->getVersion()),
            ));
        }

        if ($algorithm === EncryptionAlgorithm::AES_256 && !$this->document->getProfile()->supportsAes256Encryption()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow AES-256 encryption. PDF 1.7 or higher is required.',
                PdfVersion::format($this->document->getVersion()),
            ));
        }
    }

    public function assertAllowsAttachments(): void
    {
        if ($this->document->getProfile()->supportsEmbeddedFileAttachments()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow embedded file attachments.',
            $this->document->getProfile()->name(),
        ));
    }

    public function assertAllowsAssociatedFiles(): void
    {
        if ($this->document->getProfile()->supportsAssociatedFiles()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'PDF version %s does not allow associated files. PDF 2.0 or a supporting archival profile is required.',
            PdfVersion::format($this->document->getVersion()),
        ));
    }

    public function assertAllowsOptionalContentGroups(): void
    {
        if (!$this->document->getProfile()->supportsCurrentOptionalContentGroupImplementation()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow optional content groups (layers).',
                $this->document->getProfile()->name(),
            ));
        }

        if (!$this->document->getProfile()->supportsOptionalContentGroups()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow optional content groups (layers). PDF 1.5 or higher is required.',
                PdfVersion::format($this->document->getVersion()),
            ));
        }
    }

    public function assertAllowsTransparency(): void
    {
        if (!$this->document->getProfile()->supportsCurrentTransparencyImplementation()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow transparency in the current implementation.',
                $this->document->getProfile()->name(),
            ));
        }

        if (!$this->document->getProfile()->supportsTransparency()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow transparency. PDF 1.4 or higher is required.',
                PdfVersion::format($this->document->getVersion()),
            ));
        }
    }

    /**
     * @param array{
     *     baseFont: string,
     *     subtype: string,
     *     encoding: string,
     *     unicode: bool,
     *     fontFilePath: ?string
     * } $options
     */
    public function assertAllowsFontRegistration(array $options): void
    {
        if (
            !$options['unicode']
            && $options['encoding'] === 'WinAnsiEncoding'
            && !$this->document->getProfile()->supportsWinAnsiEncoding()
        ) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow WinAnsiEncoding for standard fonts. PDF 1.1 or higher is required.',
                PdfVersion::format($this->document->getVersion()),
            ));
        }

        if (!$this->document->getProfile()->requiresEmbeddedUnicodeFonts()) {
            return;
        }

        if (StandardFontName::isValid($options['baseFont']) && $options['fontFilePath'] === null) {
            throw new InvalidArgumentException(sprintf(
                "Profile %s does not allow PDF standard fonts like '%s'. Register an embedded Unicode font instead.",
                $this->document->getProfile()->name(),
                $options['baseFont'],
            ));
        }

        if ($options['fontFilePath'] === null || !$options['unicode']) {
            throw new InvalidArgumentException(sprintf(
                "Profile %s requires embedded Unicode fonts in the current implementation. Font '%s' must provide an embedded font file and Unicode mapping.",
                $this->document->getProfile()->name(),
                $options['baseFont'],
            ));
        }
    }

    public function assertAllowsForms(): void
    {
        if ($this->document->getProfile()->supportsAcroForms()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow AcroForm fields in the current implementation.',
            $this->document->getProfile()->name(),
        ));
    }

    private function assertAllowsEncryption(): void
    {
        if ($this->document->getProfile()->supportsEncryption()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow encryption.',
            $this->document->getProfile()->name(),
        ));
    }
}
