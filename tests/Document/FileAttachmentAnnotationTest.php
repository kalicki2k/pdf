<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\FileAttachmentAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\EmbeddedFileStream;
use Kalle\Pdf\Document\FileSpecification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileAttachmentAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_file_attachment_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification, 'Graph', 'Anhang');

        self::assertSame(
            "9 0 obj\n"
            . "<< /Type /Annot /Subtype /FileAttachment /Rect [10 20 22 34] /P 4 0 R /FS 8 0 R /Name /Graph /Contents (Anhang) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
