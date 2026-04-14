<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Attachment;

use Kalle\Pdf\Document\Attachment\MimeType;
use PHPUnit\Framework\TestCase;

final class MimeTypeTest extends TestCase
{
    public function testItDetectsCommonMimeTypesFromFilename(): void
    {
        self::assertSame(MimeType::XML, MimeType::fromFilename('invoice.xml'));
        self::assertSame(MimeType::PDF, MimeType::fromFilename('archive.PDF'));
        self::assertSame(MimeType::JSON, MimeType::fromFilename('payload.json'));
        self::assertSame(MimeType::PLAIN_TEXT, MimeType::fromFilename('notes.log'));
        self::assertSame(MimeType::HTML, MimeType::fromFilename('index.htm'));
        self::assertSame(MimeType::CSV, MimeType::fromFilename('export.csv'));
        self::assertSame(MimeType::ZIP, MimeType::fromFilename('bundle.zip'));
        self::assertSame(MimeType::WORDPROCESSING_ML, MimeType::fromFilename('offer.docx'));
        self::assertSame(MimeType::SPREADSHEET_ML, MimeType::fromFilename('report.xlsx'));
        self::assertSame(MimeType::PRESENTATION_ML, MimeType::fromFilename('slides.pptx'));
        self::assertSame(MimeType::JPEG, MimeType::fromFilename('photo.jpeg'));
        self::assertSame(MimeType::TIFF, MimeType::fromFilename('scan.tif'));
        self::assertSame(MimeType::SVG, MimeType::fromFilename('diagram.svg'));
    }

    public function testItFallsBackToOctetStreamForUnknownExtensions(): void
    {
        self::assertSame(MimeType::OCTET_STREAM, MimeType::fromFilename('payload.unknown'));
        self::assertSame(MimeType::OCTET_STREAM, MimeType::fromFilename('payload'));
    }
}
