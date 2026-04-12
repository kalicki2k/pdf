<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\TaggedPdf;

use Kalle\Pdf\Document\TaggedPdf\StructElem;
use PHPUnit\Framework\TestCase;

final class StructElemTest extends TestCase
{
    public function testItRendersCustomKidEntries(): void
    {
        $structElem = new StructElem(
            'Link',
            4,
            pageObjectId: 3,
            altText: 'Open Example',
            kidEntries: ['<< /Type /OBJR /Obj 8 0 R /Pg 3 0 R >>'],
        );

        self::assertSame(
            '<< /Type /StructElem /S /Link /P 4 0 R /Pg 3 0 R /Alt (Open Example) /K [<< /Type /OBJR /Obj 8 0 R /Pg 3 0 R >>] >>',
            $structElem->objectContents(),
        );
    }
}
