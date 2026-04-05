<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\RadioButtonAppearanceStream;
use Kalle\Pdf\Document\RadioButtonField;
use Kalle\Pdf\Document\RadioButtonWidgetAnnotation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RadioButtonWidgetAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_radio_button_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $field = new RadioButtonField(7, 'delivery');
        $annotation = new RadioButtonWidgetAnnotation(
            8,
            $page,
            $field,
            10,
            20,
            12,
            'standard',
            true,
            new RadioButtonAppearanceStream(9, 12, false),
            new RadioButtonAppearanceStream(10, 12, true),
        );

        self::assertSame(
            "8 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /Rect [10 20 22 32] /Border [0 0 0] /P 4 0 R /Parent 7 0 R /AS /standard /AP << /N << /Off 9 0 R /standard 10 0 R >> >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }
}
