<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\LinkAnnotation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_uri_link_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, 'https://example.com');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /A << /S /URI /URI (https://example.com) >> /P 4 0 R >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
