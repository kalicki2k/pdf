<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Page\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Page\Form\RadioButtonAppearanceStream;
use Kalle\Pdf\Profile\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RadioButtonWidgetAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_radio_button_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
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
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation),
        );
    }

    #[Test]
    public function it_renders_an_unchecked_radio_button_widget_annotation_and_returns_related_objects(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $field = new RadioButtonField(7, 'delivery');
        $offAppearance = new RadioButtonAppearanceStream(9, 12, false);
        $onAppearance = new RadioButtonAppearanceStream(10, 12, true);

        $annotation = new RadioButtonWidgetAnnotation(
            8,
            $page,
            $field,
            10,
            20,
            12,
            'standard',
            false,
            $offAppearance,
            $onAppearance,
        );

        self::assertSame(
            "8 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /Rect [10 20 22 32] /Border [0 0 0] /P 4 0 R /Parent 7 0 R /AS /Off /AP << /N << /Off 9 0 R /standard 10 0 R >> >> >>\n"
            . "endobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation),
        );
        self::assertSame([$offAppearance, $onAppearance], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_structural_metadata_for_radio_button_widgets(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
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
        $annotation->withStructParent(1);

        self::assertStringContainsString('/StructParent 1', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation));
    }
}
