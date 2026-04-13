<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final class PdfA4ScopePolicy
{
    public function assertProfileSelectionAllowed(Document $document): void
    {
        if (!$document->profile->isPdfA4()) {
            return;
        }

        if ($document->profile->pdfaConformance() === 'E') {
            throw new InvalidArgumentException(
                'Profile PDF/A-4e is blocked until PDF/A-4e-specific engineering features and the PDF 2.0 validation path are implemented.',
            );
        }

        if ($document->profile->pdfaConformance() === 'F') {
            throw new InvalidArgumentException(
                'Profile PDF/A-4f is blocked until the dedicated PDF/A-4f attachment and PDF 2.0 validation path are implemented.',
            );
        }

        throw new InvalidArgumentException(
            'Profile PDF/A-4 is blocked until the dedicated PDF/A-4 policy matrix and PDF 2.0 validation path are implemented.',
        );
    }
}
