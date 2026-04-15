<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Page\AnnotationBorderStyle;
use Kalle\Pdf\Page\CaretAnnotation;
use Kalle\Pdf\Page\CircleAnnotation;
use Kalle\Pdf\Page\InkAnnotation;
use Kalle\Pdf\Page\LineAnnotation;
use Kalle\Pdf\Page\LineEndingStyle;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PolygonAnnotation;
use Kalle\Pdf\Page\PolyLineAnnotation;
use Kalle\Pdf\Page\SquareAnnotation;
use Kalle\Pdf\Page\SquigglyAnnotation;
use Kalle\Pdf\Page\StampAnnotation;
use Kalle\Pdf\Page\StrikeOutAnnotation;
use Kalle\Pdf\Page\UnderlineAnnotation;
use PHPUnit\Framework\TestCase;

final class AdditionalAnnotationsTest extends TestCase
{
    public function testItBuildsMarkupAndCommentAnnotationObjects(): void
    {
        $context = new PageAnnotationRenderContext(3, true, [1 => 3], [], 7, 11);

        self::assertSame(
            '<< /Type /Annot /Subtype /Underline /Rect [10 20 40 28] /P 3 0 R /QuadPoints [10 28 40 28 10 20 40 20] /StructParent 7 /F 4 /C [1 0 0] /Contents (Underline) /T (QA) /AP << /N 11 0 R >> >>',
            new UnderlineAnnotation(10, 20, 30, 8, Color::rgb(1, 0, 0), 'Underline', 'QA')->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /StrikeOut /Rect [10 20 40 28] /P 3 0 R /QuadPoints [10 28 40 28 10 20 40 20] /StructParent 7 /F 4 /C [0 0 1] /Contents (Strike) /T (QA) /AP << /N 11 0 R >> >>',
            new StrikeOutAnnotation(10, 20, 30, 8, Color::rgb(0, 0, 1), 'Strike', 'QA')->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Squiggly /Rect [10 20 40 28] /P 3 0 R /QuadPoints [10 28 40 28 10 20 40 20] /StructParent 7 /F 4 /C [0 0.5 0] /Contents (Squiggle) /T (QA) /AP << /N 11 0 R >> >>',
            new SquigglyAnnotation(10, 20, 30, 8, Color::rgb(0, 0.5, 0), 'Squiggle', 'QA')->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Stamp /Rect [10 20 50 38] /P 3 0 R /Name /Approved /StructParent 7 /F 4 /C [1 0 0] /Contents (Stamp) /T (QA) /AP << /N 11 0 R >> >>',
            new StampAnnotation(10, 20, 40, 18, 'Approved', Color::rgb(1, 0, 0), 'Stamp', 'QA')->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Caret /Rect [10 20 28 38] /P 3 0 R /Sy /P /StructParent 7 /F 4 /Contents (Insert) /T (QA) /AP << /N 11 0 R >> >>',
            new CaretAnnotation(10, 20, 18, 18, 'Insert', 'QA', 'P')->pdfObjectContents($context),
        );
    }

    public function testItBuildsShapeAndPathAnnotationObjects(): void
    {
        $context = new PageAnnotationRenderContext(3, true, [1 => 3], [], 7, 11);

        self::assertSame(
            '<< /Type /Annot /Subtype /Square /Rect [10 20 90 44] /P 3 0 R /StructParent 7 /F 4 /C [1 0 0] /IC [0.9] /Contents (Square) /T (QA) /BS << /W 2 /S /D /D [3 2] >> /AP << /N 11 0 R >> >>',
            new SquareAnnotation(10, 20, 80, 24, Color::rgb(1, 0, 0), Color::gray(0.9), 'Square', 'QA', AnnotationBorderStyle::dashed(2.0))->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Circle /Rect [10 20 90 44] /P 3 0 R /StructParent 7 /F 4 /C [1 0 0] /IC [0.9] /Contents (Circle) /T (QA) /BS << /W 1 /S /S >> /AP << /N 11 0 R >> >>',
            new CircleAnnotation(10, 20, 80, 24, Color::rgb(1, 0, 0), Color::gray(0.9), 'Circle', 'QA', AnnotationBorderStyle::solid())->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Ink /Rect [10 20 90 44] /P 3 0 R /InkList [[10 20 20 30 30 22] [32 24 42 28]] /StructParent 7 /F 4 /C [0] /Contents (Ink) /T (QA) /AP << /N 11 0 R >> >>',
            new InkAnnotation(10, 20, 80, 24, [[[10.0, 20.0], [20.0, 30.0], [30.0, 22.0]], [[32.0, 24.0], [42.0, 28.0]]], Color::black(), 'Ink', 'QA')->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Line /Rect [10 20 40 50] /P 3 0 R /L [10 20 40 50] /StructParent 7 /F 4 /C [0 0 1] /Contents (Line) /T (QA) /Subj (Guide) /BS << /W 2 /S /S >> /LE [/Circle /ClosedArrow] /AP << /N 11 0 R >> >>',
            new LineAnnotation(10, 20, 40, 50, Color::rgb(0, 0, 1), 'Line', 'QA', LineEndingStyle::CIRCLE, LineEndingStyle::CLOSED_ARROW, 'Guide', AnnotationBorderStyle::solid(2.0))->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /PolyLine /Rect [10 20 90 50] /P 3 0 R /Vertices [10 20 40 50 90 32] /StructParent 7 /F 4 /C [0 0 1] /Contents (Polyline) /T (QA) /Subj (Guide) /BS << /W 1 /S /S >> /LE [/None /Slash] /AP << /N 11 0 R >> >>',
            new PolyLineAnnotation([[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(0, 0, 1), 'Polyline', 'QA', null, LineEndingStyle::SLASH, 'Guide', AnnotationBorderStyle::solid())->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Polygon /Rect [10 20 90 50] /P 3 0 R /Vertices [10 20 40 50 90 32] /StructParent 7 /F 4 /C [1 0 0] /IC [0.9] /Contents (Polygon) /T (QA) /Subj (Area) /BS << /W 1 /S /S >> /AP << /N 11 0 R >> >>',
            new PolygonAnnotation([[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(1, 0, 0), Color::gray(0.9), 'Polygon', 'QA', 'Area', AnnotationBorderStyle::solid())->pdfObjectContents($context),
        );
    }

    public function testItRejectsInvalidAdditionalAnnotationArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon annotation requires at least three vertices.');

        new PolygonAnnotation([[10.0, 20.0], [40.0, 50.0]]);
    }

    public function testItRejectsInvalidCaretSymbols(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Caret annotation symbol must be "None" or "P".');

        new CaretAnnotation(10, 20, 18, 18, symbol: 'X');
    }
}
