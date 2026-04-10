<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Annotation\PopupAnnotation;
use Kalle\Pdf\Page\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Page\Annotation\UnderlineAnnotation;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnderlineAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_an_underline_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new UnderlineAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(0, 0, 255), 'Unterstrichen', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Underline /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /C [0 0 1] /Contents (Unterstrichen) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new UnderlineAnnotation(7, $page, 10, 20, 80, 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Underline /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_link_a_popup_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new UnderlineAnnotation(7, $page, 10, 20, 80, 12, contents: 'Unterstrichen');
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 40, 60, 30, true);

        self::assertSame($annotation, $annotation->withPopup($popup));
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertSame([$popup], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_cmyk_underline_color(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new UnderlineAnnotation(7, $page, 10, 20, 80, 12, Color::cmyk(0.1, 0.2, 0.3, 0.4));

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
    }

    #[Test]
    public function it_renders_a_pdf_a_underline_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new UnderlineAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(0, 0, 255), 'Unterstrichen', 'QA');
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 12));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Underline /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /F 4 /C [0 0 1] /Contents (Unterstrichen) /T (QA) /AP << /N 8 0 R >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertCount(1, $annotation->getRelatedObjects());
    }
}
