<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\Form;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Internal\Document\Form\AcroForm;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Page\Annotation\TextFieldAnnotation;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AcroFormTest extends TestCase
{
    #[Test]
    public function it_renders_an_acro_form_with_registered_fields_and_fonts(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $page->addTextField('customer_name', new Rect(10, 20, 100, 20), 'Ada', 'Helvetica', 12);

        $acroForm = $document->acroForm;

        self::assertNotNull($acroForm);
        self::assertStringContainsString('/Fields [9 0 R]', $acroForm->render());
        self::assertStringContainsString('/NeedAppearances true', $acroForm->render());
        self::assertStringContainsString('/DR << /Font << /F1 4 0 R >> >>', $acroForm->render());
    }

    #[Test]
    public function it_reuses_font_resource_names_for_the_same_base_font(): void
    {
        $acroForm = new AcroForm(1);
        $fontA = new StandardFont(4, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $fontB = new StandardFont(5, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame('F1', $acroForm->registerFont($fontA));
        self::assertSame('F1', $acroForm->registerFont($fontB));
        self::assertStringContainsString('/DR << /Font << /F1 4 0 R >> >>', $acroForm->render());
    }

    #[Test]
    public function it_rejects_fonts_that_are_not_indirect_objects(): void
    {
        $acroForm = new AcroForm(1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AcroForm fonts must be indirect objects.');

        $acroForm->registerFont(new class () implements FontDefinition {
            public function getId(): int
            {
                return 1;
            }

            public function getBaseFont(): string
            {
                return StandardFontName::HELVETICA;
            }

            public function supportsText(string $text): bool
            {
                return true;
            }

            public function encodeText(string $text): string
            {
                return $text;
            }

            public function measureTextWidth(string $text, float $size): float
            {
                return 0.0;
            }

            public function render(): string
            {
                return '';
            }
        });
    }

    #[Test]
    public function it_reuses_existing_radio_groups_and_adds_them_once_to_the_fields(): void
    {
        $acroForm = new AcroForm(1);

        $groupA = $acroForm->getOrCreateRadioGroup('delivery', 7);
        $groupB = $acroForm->getOrCreateRadioGroup('delivery', 9);

        self::assertSame($groupA, $groupB);
        self::assertCount(1, $acroForm->getFields());
        self::assertSame($groupA, $acroForm->getFields()[0]);
    }

    #[Test]
    public function it_filters_page_annotations_from_field_objects_for_render(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $acroForm = new AcroForm(1);
        $radioGroup = $acroForm->getOrCreateRadioGroup('delivery', 7);
        $textField = new TextFieldAnnotation(8, $page, 10, 20, 80, 20, 'customer_name', 'Ada', 'F1', 12);

        $acroForm->addField($textField);

        self::assertCount(2, $acroForm->getFields());
        self::assertSame([$radioGroup], $acroForm->getFieldObjectsForRender());
    }
}
