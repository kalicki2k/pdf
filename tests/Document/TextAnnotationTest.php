<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\PopupAnnotation;
use Kalle\Pdf\Document\Annotation\TextAnnotation;
use Kalle\Pdf\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_text_annotation(): void
    {
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, false);
        $annotation->withPopup($popup);

        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }
}
