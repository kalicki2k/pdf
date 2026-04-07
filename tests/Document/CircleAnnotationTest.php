<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Document\Annotation\CircleAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircleAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_circle_annotation(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24, Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Circle /Rect [10 20 90 44] /P 4 0 R /C [0 0 1] /IC [0.9] /Contents (Kreis) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_border_style_for_a_circle_annotation(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24, borderStyle: AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]));

        self::assertStringContainsString('/BS << /W 1.5 /S /D /D [2 1] >>', $annotation->render());
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Circle /Rect [10 20 90 44] /P 4 0 R >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_cmyk_border_and_fill_colors(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
            Color::cmyk(0.5, 0.6, 0.7, 0.8),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
        self::assertStringContainsString('/IC [0.5 0.6 0.7 0.8]', $annotation->render());
    }
}
