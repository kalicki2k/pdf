<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Page\AnnotationAppearanceRenderContext;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use PHPUnit\Framework\TestCase;

final class FreeTextAnnotationTest extends TestCase
{
    public function testItBuildsAFreeTextAnnotationObject(): void
    {
        $annotation = new FreeTextAnnotation(
            10,
            20,
            60,
            24,
            'Kommentar',
            'F1',
            12,
            "0 0 0 rg\nBT\n/F1 12 Tf\n2 10 Td\n(Kommentar) Tj\nET",
            Color::black(),
            Color::rgb(0.2, 0.2, 0.2),
            Color::rgb(1, 1, 0.8),
            'QA',
        );
        $context = new PageAnnotationRenderContext(3, false, [1 => 3]);
        $appearanceContext = new AnnotationAppearanceRenderContext(['F1' => 17]);

        self::assertSame(
            '<< /Type /Annot /Subtype /FreeText /Rect [10 20 70 44] /P 3 0 R /Contents (Kommentar) /DA (/F1 12 Tf 0 g) /T (QA) /C [0.2 0.2 0.2] /IC [1 1 0.8] >>',
            $annotation->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /FreeText /Rect [10 20 70 44] /P 3 0 R /Contents (Kommentar) /DA (/F1 12 Tf 0 g) /StructParent 7 /F 4 /T (QA) /C [0.2 0.2 0.2] /IC [1 1 0.8] /AP << /N 11 0 R >> >>',
            $annotation->pdfObjectContents(new PageAnnotationRenderContext(3, true, [1 => 3], [], 7, 11)),
        );
        self::assertSame(
            '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 60 24] /Resources << /Font << /F1 17 0 R >> >> /Length 47 >>',
            $annotation->appearanceStreamDictionaryContents($appearanceContext),
        );
        self::assertSame("0 0 0 rg\nBT\n/F1 12 Tf\n2 10 Td\n(Kommentar) Tj\nET", $annotation->appearanceStreamContents());
    }

    public function testItRejectsInvalidFreeTextAnnotationArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FreeText annotation contents must not be empty.');

        new FreeTextAnnotation(10, 20, 60, 24, '', 'F1', 12, '');
    }
}
