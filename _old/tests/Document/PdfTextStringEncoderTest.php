<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\PdfTextStringEncoder;
use PHPUnit\Framework\TestCase;

final class PdfTextStringEncoderTest extends TestCase
{
    public function testItKeepsAsciiLiteralStringsStable(): void
    {
        $encoder = new PdfTextStringEncoder();

        self::assertSame('(Example Title)', $encoder->encodeLiteral('Example Title'));
    }

    public function testItUsesPdfDocEncodingForUnicodeMetadataWhenPossible(): void
    {
        $encoder = new PdfTextStringEncoder();

        self::assertSame('(J\\366rg Example)', $encoder->encodeLiteral('Jörg Example'));
        self::assertSame('(Projekt\\374bersicht)', $encoder->encodeLiteral('Projektübersicht'));
    }

    public function testItFallsBackToUtf16BeWithBomForCharactersOutsidePdfDocEncoding(): void
    {
        $encoder = new PdfTextStringEncoder();

        self::assertSame('(\\376\\377\\000R\\000o\\000c\\000k\\000e\\000t\\000 \\330=\\336\\200)', $encoder->encodeLiteral('Rocket 🚀'));
    }

    public function testItEscapesParenthesesBackslashesAndControlBytesAfterPdfDocEncoding(): void
    {
        $encoder = new PdfTextStringEncoder();

        self::assertSame('(\\(J\\\\rg\\)\\012\\011\\015)', $encoder->encodeLiteral("(J\\rg)\n\t\r"));
    }

    public function testItUsesPdfDocEncodingForSupportedPdfSpecialCharacters(): void
    {
        $encoder = new PdfTextStringEncoder();

        self::assertSame('(Price \\24010 \\200 fi \\223)', $encoder->encodeLiteral('Price €10 • fi ﬁ'));
    }

    public function testItEscapesUtf16BeBytesAfterUnicodeFallbackForMixedStrings(): void
    {
        $encoder = new PdfTextStringEncoder();

        self::assertSame(
            '(\\376\\377\\000\\(\\000A\\000\\\\\\000B\\000\\)\\000 \\330=\\336\\200)',
            $encoder->encodeLiteral('(A\\B) 🚀'),
        );
    }
}
