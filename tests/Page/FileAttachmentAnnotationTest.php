<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Page\FileAttachmentAnnotation;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use PHPUnit\Framework\TestCase;

final class FileAttachmentAnnotationTest extends TestCase
{
    public function testItBuildsAFileAttachmentAnnotationObject(): void
    {
        $annotation = new FileAttachmentAnnotation(10, 20, 12, 14, 'demo.txt', 'Graph', 'Anhang');

        self::assertSame(
            '<< /Type /Annot /Subtype /FileAttachment /Rect [10 20 22 34] /P 3 0 R /FS 8 0 R /Name /Graph /Contents (Anhang) >>',
            $annotation->pdfObjectContents(new PageAnnotationRenderContext(3, false, [1 => 3], [], null, null, null, [], ['demo.txt' => 8])),
        );
    }

    public function testItRejectsInvalidFileAttachmentArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File attachment annotation filename must not be empty.');

        new FileAttachmentAnnotation(10, 20, 12, 14, '');
    }
}
