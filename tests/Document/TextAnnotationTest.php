<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\PopupAnnotation;
use Kalle\Pdf\Document\Annotation\TextAnnotation;
use Kalle\Pdf\Document\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_text_annotation(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Text /Rect [10 20 26 38] /P 4 0 R /Contents (Kommentar) /Name /Comment /Open true /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_text_annotation_with_popup_reference(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, false);
        $annotation->withPopup($popup);

        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_pdf_a_text_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 16, 18));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Text /Rect [10 20 26 38] /P 4 0 R /Contents (Kommentar) /Name /Comment /Open true /F 4 /T (QA) /AP << /N 8 0 R >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_text_annotation_with_a_struct_parent_reference(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $annotation->withStructParent(3);

        self::assertStringContainsString('/StructParent 3', $annotation->render());
    }
}
