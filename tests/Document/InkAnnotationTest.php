<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\InkAnnotation;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InkAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_an_ink_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new InkAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0], [30.0, 20.0]],
            ],
            Color::rgb(0, 0, 0),
            'Ink',
            'QA',
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Ink /Rect [10 20 90 44] /P 4 0 R /InkList [[10 20 20 30 30 20]] /C [0 0 0] /Contents (Ink) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
