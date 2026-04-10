<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Document\Serialization\DocumentPdfSerializer;
use Kalle\Pdf\Internal\Document\Serialization\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Internal\Render\PdfRenderer;
use Kalle\Pdf\Internal\Render\StringPdfOutput;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentPdfSerializerTest extends TestCase
{
    #[Test]
    public function it_writes_a_document_using_the_serialization_plan_builder_and_renderer(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $expectedOutput = (new PdfRenderer())->render((new DocumentSerializationPlanBuilder())->build($document));
        $output = new StringPdfOutput();

        (new DocumentPdfSerializer())->write($document, $output);

        self::assertSame($expectedOutput, $output->contents());
    }
}
