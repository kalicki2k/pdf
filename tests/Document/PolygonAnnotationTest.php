<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Document\Annotation\PolygonAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PolygonAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_polygon_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Polygon /Rect [10 20 90 50] /P 4 0 R /Vertices [10 20 40 50 90 32] /C [1 0 0] /IC [0.9] /Contents (Polygon) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_subject_and_popup_reference_for_a_polygon_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], subject: 'Flaechenhinweis');
        $popup = new \Kalle\Pdf\Document\Annotation\PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, true);

        $annotation->withPopup($popup);

        self::assertStringContainsString('/Subj (Flaechenhinweis)', $annotation->render());
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
    }

    #[Test]
    public function it_renders_a_border_style_for_a_polygon_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], borderStyle: AnnotationBorderStyle::dashed());

        self::assertStringContainsString('/BS << /W 1 /S /D /D [3 2] >>', $annotation->render());
    }
}
