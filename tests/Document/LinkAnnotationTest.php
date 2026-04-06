<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\LinkAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\LinkTarget;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_uri_link_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::externalUrl('https://example.com'));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /A << /S /URI /URI (https://example.com) >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_an_internal_link_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::namedDestination('table-demo'));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /Dest /table-demo >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_page_link_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $targetPage = $document->addPage();
        $annotation = new LinkAnnotation(10, $page, 10, 20, 80, 12, LinkTarget::page($targetPage));

        self::assertSame(
            "10 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /Dest [7 0 R /Fit] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_position_link_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $targetPage = $document->addPage();
        $annotation = new LinkAnnotation(10, $page, 10, 20, 80, 12, LinkTarget::position($targetPage, 15, 25));

        self::assertSame(
            "10 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /Dest [7 0 R /XYZ 15 25 null] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }
}
