<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Form\RadioButtonAppearanceStream;
use Kalle\Pdf\Document\Form\RadioButtonField;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RadioButtonFieldTest extends TestCase
{
    #[Test]
    public function it_renders_a_radio_button_parent_field(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();
        $field = new RadioButtonField(7, 'delivery');
        $field->addWidget(
            new RadioButtonWidgetAnnotation(
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
            ),
            'standard',
            true,
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /FT /Btn /T (delivery) /Ff 49152 /Kids [8 0 R] /V /standard >>\n"
            . "endobj\n",
            $field->render(),
        );
    }
}
