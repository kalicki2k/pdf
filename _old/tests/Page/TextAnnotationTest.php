<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PopupAnnotationDefinition;
use Kalle\Pdf\Page\TextAnnotation;
use PHPUnit\Framework\TestCase;

final class TextAnnotationTest extends TestCase
{
    public function testItBuildsATextAnnotationObject(): void
    {
        $annotation = new TextAnnotation(10, 20, 18, 18, 'Kommentar', 'QA', 'Comment', true);
        $context = new PageAnnotationRenderContext(3, false, [1 => 3]);

        self::assertSame(
            '<< /Type /Annot /Subtype /Text /Rect [10 20 28 38] /P 3 0 R /Contents (Kommentar) /Name /Comment /Open true /T (QA) >>',
            $annotation->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Text /Rect [10 20 28 38] /P 3 0 R /Contents (Kommentar) /Name /Comment /Open true /StructParent 7 /F 4 /T (QA) /AP << /N 11 0 R >> >>',
            $annotation->pdfObjectContents(new PageAnnotationRenderContext(3, true, [1 => 3], [], 7, 11)),
        );
        self::assertSame(
            '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 18 18] /Resources << >> /Length 26 >>',
            $annotation->appearanceStreamDictionaryContents(),
        );
        self::assertSame("1 g\n0 G\n1 w\n0 0 18 18 re\nB", $annotation->appearanceStreamContents());
    }

    public function testItCanReferenceAPopupAnnotation(): void
    {
        $annotation = new TextAnnotation(10, 20, 18, 18, 'Kommentar')
            ->withPopup(new PopupAnnotationDefinition(20, 30, 60, 40, true));
        $context = new PageAnnotationRenderContext(3, false, [1 => 3], [], null, null, 5, [6]);

        self::assertSame(
            '<< /Type /Annot /Subtype /Text /Rect [10 20 28 38] /P 3 0 R /Contents (Kommentar) /Name /Note /Open false /Popup 6 0 R >>',
            $annotation->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Popup /Rect [20 30 80 70] /P 3 0 R /Parent 5 0 R /Open true >>',
            $annotation->relatedObjects($context)[0]->contents,
        );
    }

    public function testItRejectsInvalidTextAnnotationArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text annotation contents must not be empty.');

        new TextAnnotation(10, 20, 18, 18, '');
    }
}
