<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Page\AnnotationBorderStyle;
use Kalle\Pdf\Page\AnnotationBorderStyleType;
use PHPUnit\Framework\TestCase;

final class AnnotationStyleTest extends TestCase
{
    public function testItBuildsAnnotationBorderStylePdfDictionaries(): void
    {
        self::assertSame('<< /W 2.5 /S /S >>', AnnotationBorderStyle::solid(2.5)->pdfDictionaryContents());
        self::assertSame(
            '<< /W 1.5 /S /D /D [2 1] >>',
            AnnotationBorderStyle::dashed(1.5, [2.0, 1.0])->pdfDictionaryContents(),
        );
        self::assertSame(
            '<< /W 1 /S /D >>',
            (new AnnotationBorderStyle(1.0, AnnotationBorderStyleType::DASHED))->pdfDictionaryContents(),
        );
    }

    public function testItRejectsInvalidAnnotationBorderStyles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation border width must be zero or greater.');

        new AnnotationBorderStyle(-1.0);
    }
}
