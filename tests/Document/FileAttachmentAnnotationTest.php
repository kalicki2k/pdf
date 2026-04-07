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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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

    #[Test]
    public function it_uses_the_default_icon_and_omits_empty_contents(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification);

        self::assertSame(
            "9 0 obj\n"
            . "<< /Type /Annot /Subtype /FileAttachment /Rect [10 20 22 34] /P 4 0 R /FS 8 0 R /Name /PushPin >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_file_attachment_annotation_with_a_struct_parent_reference(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification, 'Graph', 'Anhang');
        $annotation->withStructParent(4);

        self::assertStringContainsString('/StructParent 4', $annotation->render());
    }
}
