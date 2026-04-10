<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Document\Serialization\DocumentPdfWriter;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentPdfWriterTest extends TestCase
{
    #[Test]
    public function it_applies_document_render_preparation_before_serialization(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $document->addHeader(static function (Page $page, int $pageNumber): void {
            $page->addText("Header $pageNumber", new Position(10, 90), 'Helvetica', 10);
        });
        $document->addPage(100, 100);
        $output = new StringPdfOutput();

        (new DocumentPdfWriter())->write($document, $output);

        self::assertStringContainsString('(Header 1) Tj', $output->contents());
    }
}
