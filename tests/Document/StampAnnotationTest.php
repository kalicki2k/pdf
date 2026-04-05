<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\StampAnnotation;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StampAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_stamp_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new StampAnnotation(7, $page, 10, 20, 80, 24, 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Stamp /Rect [10 20 90 44] /P 4 0 R /Name /Approved /C [0 0.501961 0] /Contents (Freigegeben) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
