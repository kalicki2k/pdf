<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\SquigglyAnnotation;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SquigglyAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_squiggly_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $annotation = new SquigglyAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(255, 0, 255), 'Wellig', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Squiggly /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /C [1 0 1] /Contents (Wellig) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
