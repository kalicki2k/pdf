<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\ListBoxAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Form\FormFieldFlags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListBoxAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_list_box_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            'F1',
            12,
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Ch /Rect [10 20 90 60] /Border [0 0 1] /P 5 0 R /T (topics) /DA (/F1 12 Tf 0 g) /Opt [[(pdf) (PDF)] [(forms) (Forms)] [(tables) (Tables)]] /V (forms) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_read_only_and_required_flags_for_list_boxes(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF'],
            'pdf',
            'F1',
            12,
            new FormFieldFlags(readOnly: true, required: true),
        );

        self::assertStringContainsString('/Ff 3', $annotation->render());
    }

    #[Test]
    public function it_renders_a_multi_select_list_box_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            ['pdf', 'forms'],
            'F1',
            12,
            new FormFieldFlags(multiSelect: true),
        );

        self::assertStringContainsString('/Ff 2097152', $annotation->render());
        self::assertStringContainsString('/V [(pdf) (forms)]', $annotation->render());
    }

    #[Test]
    public function it_renders_a_default_value_for_list_boxes(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms'],
            'forms',
            'F1',
            12,
            defaultValue: 'pdf',
        );

        self::assertStringContainsString('/V (forms)', $annotation->render());
        self::assertStringContainsString('/DV (pdf)', $annotation->render());
    }

    #[Test]
    public function it_renders_a_multi_select_default_value_for_list_boxes(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms'],
            ['forms'],
            'F1',
            12,
            new FormFieldFlags(multiSelect: true),
            defaultValue: ['pdf', 'forms'],
        );

        self::assertStringContainsString('/DV [(pdf) (forms)]', $annotation->render());
    }
}
