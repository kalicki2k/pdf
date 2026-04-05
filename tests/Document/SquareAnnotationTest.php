<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\AnnotationBorderStyle;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\SquareAnnotation;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SquareAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_square_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquareAnnotation(7, $page, 10, 20, 80, 24, Color::rgb(255, 0, 0), Color::gray(0.9), 'Kasten', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Square /Rect [10 20 90 44] /P 4 0 R /C [1 0 0] /IC [0.9] /Contents (Kasten) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_border_style_for_a_square_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquareAnnotation(7, $page, 10, 20, 80, 24, borderStyle: AnnotationBorderStyle::solid(2.0));

        self::assertStringContainsString('/BS << /W 2 /S /S >>', $annotation->render());
    }
}
