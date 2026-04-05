<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\TextFieldAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\FormFieldFlags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextFieldAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_text_field_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new TextFieldAnnotation(7, $page, 10, 20, 80, 12, 'customer_name', 'Ada', 'F1', 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Tx /Rect [10 20 90 32] /Border [0 0 1] /P 5 0 R /T (customer_name) /DA (/F1 12 Tf 0 g) /V (Ada) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_multiline_text_field_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new TextFieldAnnotation(7, $page, 10, 20, 80, 30, 'notes', "Line 1\nLine 2", 'F1', 12, true);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Tx /Rect [10 20 90 50] /Border [0 0 1] /P 5 0 R /T (notes) /DA (/F1 12 Tf 0 g) /Ff 4096 /V (Line 1\\nLine 2) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_read_only_required_password_flags_for_text_fields(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new TextFieldAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'secret',
            'value',
            'F1',
            12,
            false,
            new FormFieldFlags(readOnly: true, required: true, password: true),
        );

        self::assertStringContainsString('/Ff 8195', $annotation->render());
    }

    #[Test]
    public function it_renders_a_default_value_for_text_fields(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new TextFieldAnnotation(7, $page, 10, 20, 80, 12, 'customer_name', 'Ada', 'F1', 12, defaultValue: 'Grace');

        self::assertStringContainsString('/V (Ada)', $annotation->render());
        self::assertStringContainsString('/DV (Grace)', $annotation->render());
    }
}
