<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentPdfWriter;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentPdfWriterTest extends TestCase
{
    #[Test]
    public function it_writes_a_document_using_the_serialization_plan_builder_and_renderer(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $expectedOutput = (new PdfRenderer())->render((new DocumentSerializationPlanBuilder())->build($document));
        $output = new StringPdfOutput();

        (new DocumentPdfWriter())->write($document, $output);

        self::assertSame($expectedOutput, $output->contents());
    }

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
