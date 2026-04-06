<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\SignatureFieldAnnotation;
use Kalle\Pdf\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SignatureFieldAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_signature_field_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $annotation = new SignatureFieldAnnotation(7, $page, 10, 20, 100, 30, 'approval_signature');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Sig /Rect [10 20 110 50] /Border [0 0 1] /P 4 0 R /T (approval_signature) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }
}
