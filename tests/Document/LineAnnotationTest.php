<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\AnnotationBorderStyle;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\LineAnnotation;
use Kalle\Pdf\Document\LineEndingStyle;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LineAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_line_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LineAnnotation(7, $page, 10, 20, 90, 32, Color::rgb(255, 0, 0), 'Linie', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Line /Rect [10 20 90 32] /P 4 0 R /L [10 20 90 32] /C [1 0 0] /Contents (Linie) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_line_ending_styles(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LineAnnotation(
            7,
            $page,
            10,
            20,
            90,
            32,
            startStyle: LineEndingStyle::OPEN_ARROW,
            endStyle: LineEndingStyle::CLOSED_ARROW,
        );

        self::assertStringContainsString('/LE [/OpenArrow /ClosedArrow]', $annotation->render());
    }

    #[Test]
    public function it_renders_subject_and_popup_reference_for_a_line_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LineAnnotation(7, $page, 10, 20, 90, 32, subject: 'Messlinie');
        $popup = new \Kalle\Pdf\Document\PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, true);

        $annotation->withPopup($popup);

        self::assertStringContainsString('/Subj (Messlinie)', $annotation->render());
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
    }

    #[Test]
    public function it_renders_a_border_style_for_a_line_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LineAnnotation(7, $page, 10, 20, 90, 32, borderStyle: AnnotationBorderStyle::dashed(2.0, [4.0, 2.0]));

        self::assertStringContainsString('/BS << /W 2 /S /D /D [4 2] >>', $annotation->render());
    }
}
