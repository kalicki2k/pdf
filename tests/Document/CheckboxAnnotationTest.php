<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\CheckboxAnnotation;
use Kalle\Pdf\Document\CheckboxAppearanceStream;
use Kalle\Pdf\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckboxAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_checkbox_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $annotation = new CheckboxAnnotation(
            7,
            $page,
            10,
            20,
            12,
            12,
            'accept_terms',
            true,
            new CheckboxAppearanceStream(8, 12, 12, false),
            new CheckboxAppearanceStream(9, 12, 12, true),
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Btn /Rect [10 20 22 32] /Border [0 0 0] /P 4 0 R /T (accept_terms) /V /Yes /AS /Yes /AP << /N << /Off 8 0 R /Yes 9 0 R >> >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
