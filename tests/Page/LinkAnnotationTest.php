<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use PHPUnit\Framework\TestCase;

final class LinkAnnotationTest extends TestCase
{
    public function testItBuildsAnExternalLinkAnnotationObject(): void
    {
        $annotation = new LinkAnnotation(LinkTarget::externalUrl('https://example.com'), 10, 20, 80, 12, 'Open Example');
        $context = new PageAnnotationRenderContext(3, false, [1 => 3]);

        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 3 0 R /A << /S /URI /URI (https://example.com) >> /Contents (Open Example) >>',
            $annotation->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 3 0 R /StructParent 7 /A << /S /URI /URI (https://example.com) >> /F 4 /Contents (Open Example) >>',
            $annotation->pdfObjectContents(new PageAnnotationRenderContext(3, true, [1 => 3], [], 7)),
        );
    }

    public function testItBuildsInternalPageAndPositionLinkAnnotationObjects(): void
    {
        $pageLink = new LinkAnnotation(LinkTarget::page(2), 10, 20, 80, 12);
        $positionLink = new LinkAnnotation(LinkTarget::position(2, 15, 25), 10, 20, 80, 12);
        $namedDestinationLink = new LinkAnnotation(LinkTarget::namedDestination('chapter-1'), 10, 20, 80, 12);
        $context = new PageAnnotationRenderContext(3, false, [1 => 3, 2 => 7], ['chapter-1' => '/chapter-1']);

        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 3 0 R /Dest [7 0 R /Fit] >>',
            $pageLink->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 3 0 R /Dest [7 0 R /XYZ 15 25 null] >>',
            $positionLink->pdfObjectContents($context),
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 3 0 R /Dest /chapter-1 >>',
            $namedDestinationLink->pdfObjectContents($context),
        );
    }

    public function testItRejectsInvalidLinkAnnotationArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link annotation width must be greater than zero.');

        new LinkAnnotation(LinkTarget::externalUrl('https://example.com'), 10, 20, 0, 12);
    }
}
