<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Action\ButtonAction;
use Kalle\Pdf\Document\Annotation\CheckboxAnnotation;
use Kalle\Pdf\Document\Annotation\ComboBoxAnnotation;
use Kalle\Pdf\Document\Annotation\ListBoxAnnotation;
use Kalle\Pdf\Document\Annotation\PushButtonAnnotation;
use Kalle\Pdf\Document\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Document\Annotation\SignatureFieldAnnotation;
use Kalle\Pdf\Document\Annotation\TextFieldAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Form\FormWidgetFactory;
use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormWidgetFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_text_field_and_registers_the_resolved_font(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $annotation = $factory->createTextField(
            'customer_name',
            new Rect(10, 20, 100, 24),
            'Ada',
            StandardFontName::HELVETICA,
            12,
            true,
            Color::rgb(0, 0, 255),
            new FormFieldFlags(required: true),
            'Grace',
        );

        self::assertInstanceOf(TextFieldAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertStringContainsString('/T (customer_name)', $annotation->render());
        self::assertStringContainsString('/DA (/F1 12 Tf 0 0 1 rg)', $annotation->render());
        self::assertStringContainsString('/V (Ada)', $annotation->render());
        self::assertStringContainsString('/DV (Grace)', $annotation->render());
        self::assertStringContainsString('/Ff 4098', $annotation->render());
    }

    #[Test]
    public function it_creates_a_checkbox_with_appearance_streams(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $annotation = $factory->createCheckbox('terms', new Position(10, 20), 12, true);

        self::assertInstanceOf(CheckboxAnnotation::class, $annotation);
        self::assertCount(2, $annotation->getRelatedObjects());
        self::assertStringContainsString('/T (terms)', $annotation->render());
        self::assertStringContainsString('/V /Yes', $annotation->render());
    }

    #[Test]
    public function it_creates_and_reuses_a_radio_button_group(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $acroForm = null;
        $factory = $this->createFactory($page, $resolvedFonts, $acroForm);

        [$groupA, $annotationA] = $factory->createRadioButton('delivery', 'standard', new Position(10, 20), 12, true);
        [$groupB, $annotationB] = $factory->createRadioButton('delivery', 'express', new Position(30, 20), 12, false);

        self::assertInstanceOf(RadioButtonField::class, $groupA);
        self::assertSame($groupA, $groupB);
        self::assertInstanceOf(RadioButtonWidgetAnnotation::class, $annotationA);
        self::assertInstanceOf(RadioButtonWidgetAnnotation::class, $annotationB);
        self::assertCount(1, $acroForm?->getFields() ?? []);
        self::assertSame([$groupA], $acroForm?->getFields());
    }

    #[Test]
    public function it_creates_a_combo_box_and_registers_the_resolved_font(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $annotation = $factory->createComboBox(
            'delivery',
            new Rect(10, 20, 100, 24),
            ['standard' => 'Standard', 'express' => 'Express'],
            'express',
            StandardFontName::HELVETICA,
            12,
            Color::rgb(255, 0, 0),
            new FormFieldFlags(required: true, editable: true),
            'standard',
        );

        self::assertInstanceOf(ComboBoxAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertStringContainsString('/T (delivery)', $annotation->render());
        self::assertStringContainsString('/V (express)', $annotation->render());
        self::assertStringContainsString('/DV (standard)', $annotation->render());
        self::assertStringContainsString('/Ff 393218', $annotation->render());
    }

    #[Test]
    public function it_creates_a_list_box_with_multiple_selected_values(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $annotation = $factory->createListBox(
            'features',
            new Rect(10, 20, 100, 40),
            ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma'],
            ['a', 'c'],
            StandardFontName::HELVETICA,
            12,
            null,
            new FormFieldFlags(multiSelect: true),
            ['b'],
        );

        self::assertInstanceOf(ListBoxAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertStringContainsString('/T (features)', $annotation->render());
        self::assertStringContainsString('/V [(a) (c)]', $annotation->render());
        self::assertStringContainsString('/DV [(b)]', $annotation->render());
        self::assertStringContainsString('/Ff 2097152', $annotation->render());
    }

    #[Test]
    public function it_creates_a_list_box_with_a_single_selected_value(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $annotation = $factory->createListBox(
            'delivery',
            new Rect(10, 20, 100, 40),
            ['standard' => 'Standard', 'express' => 'Express'],
            'express',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
        );

        self::assertInstanceOf(ListBoxAnnotation::class, $annotation);
        self::assertStringContainsString('/V (express)', $annotation->render());
    }

    #[Test]
    public function it_creates_a_signature_field(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $annotation = $factory->createSignatureField('signature', new Rect(10, 20, 100, 30));

        self::assertInstanceOf(SignatureFieldAnnotation::class, $annotation);
        self::assertStringContainsString('/T (signature)', $annotation->render());
    }

    #[Test]
    public function it_creates_a_push_button_and_registers_the_resolved_font(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);
        $action = new class () implements ButtonAction {
            public function toPdfDictionary(): DictionaryType
            {
                return new DictionaryType([
                    'S' => new NameType('ResetForm'),
                ]);
            }
        };

        $annotation = $factory->createPushButton(
            'submit',
            'Senden',
            new Rect(10, 20, 100, 24),
            StandardFontName::HELVETICA,
            12,
            null,
            $action,
        );

        self::assertInstanceOf(PushButtonAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertStringContainsString('/T (submit)', $annotation->render());
        self::assertStringContainsString('/MK << /CA (Senden) >>', $annotation->render());
        self::assertStringContainsString('/A << /S /ResetForm >>', $annotation->render());
    }

    #[Test]
    public function it_rejects_invalid_widget_inputs(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts);

        $cases = [
            ['Text field name must not be empty.', fn (): TextFieldAnnotation => $factory->createTextField('', new Rect(10, 20, 100, 20), null, StandardFontName::HELVETICA, 12, false, null, null, null)],
            ['Text field width must be greater than zero.', fn (): TextFieldAnnotation => $factory->createTextField('name', new Rect(10, 20, 0, 20), null, StandardFontName::HELVETICA, 12, false, null, null, null)],
            ['Text field height must be greater than zero.', fn (): TextFieldAnnotation => $factory->createTextField('name', new Rect(10, 20, 100, 0), null, StandardFontName::HELVETICA, 12, false, null, null, null)],
            ['Text field font size must be greater than zero.', fn (): TextFieldAnnotation => $factory->createTextField('name', new Rect(10, 20, 100, 20), null, StandardFontName::HELVETICA, 0, false, null, null, null)],
            ['Checkbox name must not be empty.', fn (): CheckboxAnnotation => $factory->createCheckbox('', new Position(10, 20), 12, false)],
            ['Checkbox size must be greater than zero.', fn (): CheckboxAnnotation => $factory->createCheckbox('terms', new Position(10, 20), 0, false)],
            ['Radio button name must not be empty.', fn (): array => $factory->createRadioButton('', 'yes', new Position(10, 20), 12, false)],
            ['Radio button value may contain only letters, numbers, dots, underscores and hyphens.', fn (): array => $factory->createRadioButton('delivery', 'bad value', new Position(10, 20), 12, false)],
            ['Radio button size must be greater than zero.', fn (): array => $factory->createRadioButton('delivery', 'yes', new Position(10, 20), 0, false)],
            ['Combo box name must not be empty.', fn (): ComboBoxAnnotation => $factory->createComboBox('', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box width must be greater than zero.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 0, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box height must be greater than zero.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 0), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box font size must be greater than zero.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 0, null, null, null)],
            ['Combo box options must not be empty.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 20), [], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box option values must not be empty.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 20), ['' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box option labels must not be empty.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 20), ['a' => ''], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box value must reference one of the available options.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], 'b', StandardFontName::HELVETICA, 12, null, null, null)],
            ['Combo box default value must reference one of the available options.', fn (): ComboBoxAnnotation => $factory->createComboBox('delivery', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, 'b')],
            ['List box name must not be empty.', fn (): ListBoxAnnotation => $factory->createListBox('', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box width must be greater than zero.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 0, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box height must be greater than zero.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 0), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box font size must be greater than zero.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 0, null, null, null)],
            ['List box options must not be empty.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), [], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box option values must not be empty.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), ['' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box option labels must not be empty.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), ['a' => ''], null, StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box value must reference one of the available options.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], 'b', StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box value must reference one of the available options.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], ['a', 'b'], StandardFontName::HELVETICA, 12, null, null, null)],
            ['List box default value must reference one of the available options.', fn (): ListBoxAnnotation => $factory->createListBox('features', new Rect(10, 20, 100, 20), ['a' => 'Alpha'], null, StandardFontName::HELVETICA, 12, null, null, 'b')],
            ['Signature field name must not be empty.', fn (): SignatureFieldAnnotation => $factory->createSignatureField('', new Rect(10, 20, 100, 20))],
            ['Signature field width must be greater than zero.', fn (): SignatureFieldAnnotation => $factory->createSignatureField('signature', new Rect(10, 20, 0, 20))],
            ['Signature field height must be greater than zero.', fn (): SignatureFieldAnnotation => $factory->createSignatureField('signature', new Rect(10, 20, 100, 0))],
            ['Push button name must not be empty.', fn (): PushButtonAnnotation => $factory->createPushButton('', 'Senden', new Rect(10, 20, 100, 20), StandardFontName::HELVETICA, 12, null, null)],
            ['Push button label must not be empty.', fn (): PushButtonAnnotation => $factory->createPushButton('submit', '', new Rect(10, 20, 100, 20), StandardFontName::HELVETICA, 12, null, null)],
            ['Push button width must be greater than zero.', fn (): PushButtonAnnotation => $factory->createPushButton('submit', 'Senden', new Rect(10, 20, 0, 20), StandardFontName::HELVETICA, 12, null, null)],
            ['Push button height must be greater than zero.', fn (): PushButtonAnnotation => $factory->createPushButton('submit', 'Senden', new Rect(10, 20, 100, 0), StandardFontName::HELVETICA, 12, null, null)],
            ['Push button font size must be greater than zero.', fn (): PushButtonAnnotation => $factory->createPushButton('submit', 'Senden', new Rect(10, 20, 100, 20), StandardFontName::HELVETICA, 0, null, null)],
        ];

        foreach ($cases as [$expectedMessage, $callback]) {
            try {
                $callback();
                self::fail("Expected exception with message: $expectedMessage");
            } catch (InvalidArgumentException $exception) {
                self::assertSame($expectedMessage, $exception->getMessage());
            }
        }
    }

    /**
     * @param list<string> $resolvedFonts
     */
    private function createFactory(Page $page, array &$resolvedFonts, ?AcroForm &$acroForm = null): FormWidgetFactory
    {
        $nextObjectId = 100;
        $acroForm ??= new AcroForm(90);

        return new FormWidgetFactory(
            $page,
            static function () use (&$nextObjectId): int {
                return $nextObjectId++;
            },
            static function () use (&$acroForm): AcroForm {
                return $acroForm;
            },
            static function (string $baseFont) use (&$resolvedFonts): StandardFont {
                $resolvedFonts[] = $baseFont;

                return new StandardFont(
                    999,
                    $baseFont,
                    'Type1',
                    'WinAnsiEncoding',
                    1.4,
                );
            },
        );
    }
}
