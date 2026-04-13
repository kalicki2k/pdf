<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Form;

use InvalidArgumentException;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormField;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Document\Form\RadioButtonChoice;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\WidgetFormField;
use PHPUnit\Framework\TestCase;

final class AcroFormTest extends TestCase
{
    public function testItBuildsAnAcroFormDictionaryForRegisteredFields(): void
    {
        $acroForm = new AcroForm()->withField($this->testField('customer_name'));

        self::assertCount(1, $acroForm->fields);
        self::assertSame('<< /Fields [7 0 R] /NeedAppearances true >>', $acroForm->pdfObjectContents([7]));
    }

    public function testItRejectsDuplicateFieldNames(): void
    {
        $acroForm = new AcroForm()->withField($this->testField('customer_name'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AcroForm field "customer_name" is already registered.');

        $acroForm->withField($this->testField('customer_name'));
    }

    public function testItCanReplaceExistingFieldsByName(): void
    {
        $acroForm = new AcroForm()
            ->withField($this->testField('customer_name'))
            ->replacingField(new ComboBoxField('customer_name', 1, 10, 20, 80, 12, ['a' => 'A']));

        self::assertInstanceOf(ComboBoxField::class, $acroForm->field('customer_name'));
        self::assertCount(1, $acroForm->fields);
    }

    public function testItBuildsDefaultResourcesWhenChoiceOrTextFieldsNeedThem(): void
    {
        $acroForm = new AcroForm()->withField(
            new ComboBoxField('status', 1, 10, 20, 80, 12, ['new' => 'New'], 'new'),
        );

        self::assertStringContainsString('/DR << /Font << /Helv << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>', $acroForm->pdfObjectContents([7]));
    }

    public function testItBuildsEmbeddedDefaultResourcesWhenAnEmbeddedFormFontIsProvided(): void
    {
        $acroForm = new AcroForm()->withField(
            new ComboBoxField('status', 1, 10, 20, 80, 12, ['new' => 'New'], 'new'),
        );

        self::assertStringContainsString('/DR << /Font << /F0 11 0 R >> >>', $acroForm->pdfObjectContents([7], 11));
        self::assertStringNotContainsString('/Helv', $acroForm->pdfObjectContents([7], 11));
    }

    public function testItRejectsTheBuiltinHelvFallbackWhenDisabled(): void
    {
        $acroForm = new AcroForm()->withField(
            new ComboBoxField('status', 1, 10, 20, 80, 12, ['new' => 'New'], 'new'),
        );

        try {
            $acroForm->pdfObjectContents([7], allowBuiltinDefaultTextFontFallback: false);
            self::fail('Expected coded build-state validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::BUILD_STATE_INVALID, $exception->error);
            self::assertSame(
                'PDF/A form resources require an embedded default font. The built-in /Helv fallback is not allowed.',
                $exception->getMessage(),
            );
        }
    }

    public function testItStoresRadioButtonGroupsAsSingleFields(): void
    {
        $group = new RadioButtonGroup('delivery', alternativeName: 'Delivery method')
            ->withChoice(new RadioButtonChoice(1, 10, 20, 12, 'standard'))
            ->withChoice(new RadioButtonChoice(1, 30, 20, 12, 'express', true));

        $acroForm = new AcroForm()->withField($group);

        self::assertCount(1, $acroForm->fields);
        self::assertSame($group, $acroForm->field('delivery'));
    }

    public function testItSetsSignatureFlagsWhenSignatureFieldsArePresent(): void
    {
        $acroForm = new AcroForm()->withField(
            new SignatureField('approval_signature', 1, 10, 20, 100, 30, 'Approval signature'),
        );

        self::assertStringContainsString('/SigFlags 1', $acroForm->pdfObjectContents([7]));
    }

    public function testItBuildsCommonWidgetEntries(): void
    {
        $field = $this->testWidgetField();
        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7);

        self::assertStringContainsString('/Subtype /Widget', $contents);
        self::assertStringContainsString('/Rect [10 20 90 32]', $contents);
        self::assertStringContainsString('/P 3 0 R', $contents);
        self::assertStringContainsString('/T (customer_name)', $contents);
        self::assertStringContainsString('/TU (Customer name)', $contents);
        self::assertStringContainsString('/FT /Tx', $contents);
    }

    public function testItRaisesACodedBuildStateErrorWhenAcroFormFieldObjectIdsDoNotMatch(): void
    {
        $acroForm = new AcroForm()
            ->withField($this->testField('customer_name'))
            ->withField($this->testField('customer_email'));

        try {
            $acroForm->pdfObjectContents([7]);
            self::fail('Expected coded build-state validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::BUILD_STATE_INVALID, $exception->error);
            self::assertSame(
                'AcroForm field object IDs must match the registered field count.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRaisesACodedFormFieldPageErrorForUnknownWidgetPages(): void
    {
        $field = $this->testWidgetField(2);

        try {
            $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7);
            self::fail('Expected coded form-field page validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::FORM_FIELD_PAGE_INVALID, $exception->error);
            self::assertSame(
                'Form field target page 2 does not exist.',
                $exception->getMessage(),
            );
        }
    }

    private function testField(string $name): FormField
    {
        return new readonly class ($name) extends FormField {
            public function pdfObjectContents(
                FormFieldRenderContext $context,
                int $fieldObjectId,
                array $relatedObjectIds = [],
            ): string {
                return '<< /FT /Sig /T ' . $this->pdfString($this->name) . ' >>';
            }
        };
    }

    private function testWidgetField(int $pageNumber = 1): WidgetFormField
    {
        return new readonly class ('customer_name', $pageNumber, 10.0, 20.0, 80.0, 12.0, 'Customer name') extends WidgetFormField {
            public function pdfObjectContents(
                FormFieldRenderContext $context,
                int $fieldObjectId,
                array $relatedObjectIds = [],
            ): string {
                return '<< ' . implode(' ', [
                    ...$this->widgetDictionaryEntries($context, $fieldObjectId),
                    '/FT /Tx',
                ]) . ' >>';
            }
        };
    }
}
