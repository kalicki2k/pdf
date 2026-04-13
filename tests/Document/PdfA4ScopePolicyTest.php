<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PdfA4ScopePolicy;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class PdfA4ScopePolicyTest extends TestCase
{
    public function testItRejectsPdfA4(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4 is blocked until the dedicated PDF/A-4 policy matrix and PDF 2.0 validation path are implemented.',
        );

        (new PdfA4ScopePolicy())->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4()));
    }

    public function testItRejectsPdfA4e(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4e is blocked until PDF/A-4e-specific engineering features and the PDF 2.0 validation path are implemented.',
        );

        (new PdfA4ScopePolicy())->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4e()));
    }

    public function testItRejectsPdfA4f(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4f is blocked until the dedicated PDF/A-4f attachment and PDF 2.0 validation path are implemented.',
        );

        (new PdfA4ScopePolicy())->assertProfileSelectionAllowed(new Document(profile: Profile::pdfA4f()));
    }
}
