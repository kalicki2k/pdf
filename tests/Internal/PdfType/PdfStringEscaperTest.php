<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\PdfType;

use Kalle\Pdf\Internal\PdfType\PdfStringEscaper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PdfStringEscaperTest extends TestCase
{
    #[Test]
    public function it_escapes_pdf_special_characters(): void
    {
        $value = "\\(Line 1)\n\t" . chr(8) . "\f";

        self::assertSame('\\\\\\(Line 1\\)\\n\\t\\b\\f', PdfStringEscaper::escape($value));
    }
}
