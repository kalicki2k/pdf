<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\FreeTextAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FreeTextAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_free_text_annotation(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            'Hinweistext',
            'F1',
            12,
            Color::rgb(255, 0, 0),
            Color::gray(0.5),
            Color::gray(0.9),
            'QA',
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /FreeText /Rect [10 20 90 44] /P 4 0 R /Contents (Hinweistext) /DA (/F1 12 Tf 1 0 0 rg) /T (QA) /C [0.5] /IC [0.9] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(7, $page, 10, 20, 80, 24, 'Hinweistext', 'F1', 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /FreeText /Rect [10 20 90 44] /P 4 0 R /Contents (Hinweistext) /DA (/F1 12 Tf 0 g) >>\n"
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
        $annotation = new FreeTextAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            'Hinweistext',
            'F1',
            12,
            null,
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
            Color::cmyk(0.5, 0.6, 0.7, 0.8),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
        self::assertStringContainsString('/IC [0.5 0.6 0.7 0.8]', $annotation->render());
    }
}
