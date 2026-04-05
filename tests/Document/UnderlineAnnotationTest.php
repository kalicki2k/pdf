<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\UnderlineAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnderlineAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_an_underline_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new UnderlineAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(0, 0, 255), 'Unterstrichen', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Underline /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /C [0 0 1] /Contents (Unterstrichen) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
