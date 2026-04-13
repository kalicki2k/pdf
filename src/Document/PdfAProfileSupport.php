<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

use function sprintf;

final readonly class PdfAProfileSupport
{
    public function __construct(
        public string $profileName,
        public bool $isSupported,
        public string $supportSummary,
    ) {
    }

    public static function for(Profile $profile): ?self
    {
        if (!$profile->isPdfA()) {
            return null;
        }

        return match ($profile->name()) {
            'PDF/A-1a' => new self(
                'PDF/A-1a',
                true,
                'Supported for the current PDF/A-1a scope with tagged structure, embedded fonts, XMP/Info metadata, OutputIntent and the guarded annotation/form subset.',
            ),
            'PDF/A-1b' => new self(
                'PDF/A-1b',
                true,
                'Supported for the current PDF/A-1b scope with embedded fonts, XMP/Info metadata, OutputIntent and the guarded non-transparent rendering path.',
            ),
            'PDF/A-2a' => new self(
                'PDF/A-2a',
                true,
                'Supported for the current PDF/A-2a scope with tagged structure, embedded Unicode fonts, XMP metadata, OutputIntent and tagged link annotations; non-link page annotations and AcroForms remain blocked.',
            ),
            'PDF/A-2b' => new self(
                'PDF/A-2b',
                true,
                'Supported for the current PDF/A-2b scope with embedded fonts, XMP metadata, OutputIntent and the explicitly validated annotation subset.',
            ),
            'PDF/A-2u' => new self(
                'PDF/A-2u',
                true,
                'Supported for the current PDF/A-2u scope with extractable Unicode fonts, XMP metadata, OutputIntent and the explicitly validated annotation subset.',
            ),
            'PDF/A-3a' => new self(
                'PDF/A-3a',
                true,
                'Supported for the current PDF/A-3a scope with tagged structure, embedded Unicode fonts, XMP metadata, OutputIntent, tagged link annotations and document-level associated files; non-link page annotations and AcroForms remain blocked.',
            ),
            'PDF/A-3b' => new self(
                'PDF/A-3b',
                true,
                'Supported for the current PDF/A-3b scope with embedded fonts, XMP metadata, OutputIntent and document-level associated files.',
            ),
            'PDF/A-3u' => new self(
                'PDF/A-3u',
                true,
                'Supported for the current PDF/A-3u scope with extractable Unicode fonts, XMP metadata, OutputIntent and document-level associated files.',
            ),
            'PDF/A-4' => new self(
                'PDF/A-4',
                false,
                'PDF/A-4 requires a dedicated PDF 2.0 validation and policy matrix that is not implemented yet.',
            ),
            'PDF/A-4e' => new self(
                'PDF/A-4e',
                false,
                'PDF/A-4e-specific engineering features and the surrounding PDF 2.0 conformance scope are not implemented yet.',
            ),
            'PDF/A-4f' => new self(
                'PDF/A-4f',
                false,
                'Some attachment plumbing exists, but the full PDF/A-4f conformance scope is not modeled or validated yet.',
            ),
            default => new self(
                $profile->name(),
                false,
                'This PDF/A profile is not part of the current capability matrix.',
            ),
        };
    }

    public function assertSupported(): void
    {
        if ($this->isSupported) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s is not supported yet: %s',
            $this->profileName,
            $this->supportSummary,
        ));
    }
}
