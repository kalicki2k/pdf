<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use PHPUnit\Framework\TestCase;

final class HighlightAnnotationTest extends TestCase
{
    public function testItBuildsAHighlightAnnotationObject(): void
    {
        $annotation = new HighlightAnnotation(10, 20, 30, 8, Color::rgb(1, 1, 0), 'Markiert', 'QA');
        $context = new PageAnnotationRenderContext(3, false, [1 => 3]);

        self::assertSame(
            '<< /Type /Annot /Subtype /Highlight /Rect [10 20 40 28] /P 3 0 R /QuadPoints [10 28 40 28 10 20 40 20] /C [1 1 0] /Contents (Markiert) /T (QA) >>',
            $annotation->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Highlight /Rect [10 20 40 28] /P 3 0 R /QuadPoints [10 28 40 28 10 20 40 20] /StructParent 7 /F 4 /C [1 1 0] /Contents (Markiert) /T (QA) /AP << /N 11 0 R >> >>',
            $annotation->pdfObjectContents(new PageAnnotationRenderContext(3, true, [1 => 3], [], 7, 11)),
        );
        self::assertSame(
            '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 30 8] /Resources << >> /Length 22 >>',
            $annotation->appearanceStreamDictionaryContents(),
        );
        self::assertSame("1 1 0 rg\n0 0 30 8 re\nf", $annotation->appearanceStreamContents());
    }

    public function testItRejectsInvalidHighlightAnnotationArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight annotation width must be greater than zero.');

        new HighlightAnnotation(10, 20, 0, 8);
    }
}
