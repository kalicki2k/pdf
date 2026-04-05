<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\AnnotationBorderStyle;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\LineEndingStyle;
use Kalle\Pdf\Document\PolyLineAnnotation;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PolyLineAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_polyline_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(0, 0, 255), 'PolyLine', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /PolyLine /Rect [10 20 90 50] /P 4 0 R /Vertices [10 20 40 50 90 32] /C [0 0 1] /Contents (PolyLine) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_polyline_ending_styles(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(
            7,
            $page,
            [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]],
            startStyle: LineEndingStyle::CIRCLE,
            endStyle: LineEndingStyle::SLASH,
        );

        self::assertStringContainsString('/LE [/Circle /Slash]', $annotation->render());
    }

    #[Test]
    public function it_renders_subject_and_popup_reference_for_a_polyline_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], subject: 'Korrekturpfad');
        $popup = new \Kalle\Pdf\Document\PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, true);

        $annotation->withPopup($popup);

        self::assertStringContainsString('/Subj (Korrekturpfad)', $annotation->render());
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
    }

    #[Test]
    public function it_renders_a_border_style_for_a_polyline_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], borderStyle: AnnotationBorderStyle::solid(2.5));

        self::assertStringContainsString('/BS << /W 2.5 /S /S >>', $annotation->render());
    }
}
