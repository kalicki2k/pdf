<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\PopupAnnotation;
use Kalle\Pdf\Document\Annotation\SquigglyAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SquigglyAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_squiggly_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquigglyAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(255, 0, 255), 'Wellig', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Squiggly /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /C [1 0 1] /Contents (Wellig) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquigglyAnnotation(7, $page, 10, 20, 80, 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Squiggly /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_link_a_popup_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquigglyAnnotation(7, $page, 10, 20, 80, 12, contents: 'Wellig');
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 40, 60, 30, true);

        self::assertSame($annotation, $annotation->withPopup($popup));
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertSame([$popup], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_cmyk_squiggly_color(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquigglyAnnotation(7, $page, 10, 20, 80, 12, Color::cmyk(0.1, 0.2, 0.3, 0.4));

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
    }
}
