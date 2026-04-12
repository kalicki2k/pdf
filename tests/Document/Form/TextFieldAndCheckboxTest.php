<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Form;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonChoice;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use PHPUnit\Framework\TestCase;

final class TextFieldAndCheckboxTest extends TestCase
{
    public function testItRendersATextFieldWidgetDictionary(): void
    {
        $field = new TextField(
            name: 'customer_name',
            pageNumber: 1,
            x: 10.0,
            y: 20.0,
            width: 120.0,
            height: 18.0,
            value: 'Ada',
            alternativeName: 'Customer name',
            defaultValue: 'Default name',
            fontSize: 11.0,
        );

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7);

        self::assertStringContainsString('/Subtype /Widget', $contents);
        self::assertStringContainsString('/FT /Tx', $contents);
        self::assertStringContainsString('/T (customer_name)', $contents);
        self::assertStringContainsString('/TU (Customer name)', $contents);
        self::assertStringContainsString('/V (Ada)', $contents);
        self::assertStringContainsString('/DV (Default name)', $contents);
        self::assertStringContainsString('/DA (/Helv 11 Tf 0 g)', $contents);
    }

    public function testItRendersACheckboxWithAppearanceReferences(): void
    {
        $field = new CheckboxField(
            name: 'accept_terms',
            pageNumber: 1,
            x: 10.0,
            y: 20.0,
            size: 12.0,
            checked: true,
            alternativeName: 'Accept terms',
        );

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7, [9, 10]);

        self::assertStringContainsString('/FT /Btn', $contents);
        self::assertStringContainsString('/V /Yes', $contents);
        self::assertStringContainsString('/AS /Yes', $contents);
        self::assertStringContainsString('/Off 9 0 R', $contents);
        self::assertStringContainsString('/Yes 10 0 R', $contents);
    }

    public function testItBuildsCheckboxAppearanceStreams(): void
    {
        $field = new CheckboxField(
            name: 'accept_terms',
            pageNumber: 1,
            x: 10.0,
            y: 20.0,
            size: 12.0,
            checked: true,
        );

        $objects = $field->relatedObjects(new FormFieldRenderContext([1 => 3]), 7, [9, 10]);

        self::assertCount(2, $objects);
        self::assertSame(9, $objects[0]->objectId);
        self::assertStringContainsString('/Subtype /Form', $objects[0]->contents);
        self::assertSame(10, $objects[1]->objectId);
        self::assertStringContainsString("9.6 9.6 l\nS", $objects[1]->contents);
    }

    public function testItRejectsCheckboxesWithoutAppearanceObjectIds(): void
    {
        $field = new CheckboxField(
            name: 'accept_terms',
            pageNumber: 1,
            x: 10.0,
            y: 20.0,
            size: 12.0,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Checkbox fields require two appearance object IDs.');

        $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7, []);
    }

    public function testItRendersARadioButtonGroupAndWidgetObjects(): void
    {
        $field = (new RadioButtonGroup('delivery', alternativeName: 'Delivery method'))
            ->withChoice(new RadioButtonChoice(1, 10.0, 20.0, 12.0, 'standard'))
            ->withChoice(new RadioButtonChoice(1, 30.0, 20.0, 12.0, 'express', true, 'Express delivery'));

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7, [8, 9, 10, 11, 12, 13]);
        $objects = $field->relatedObjects(new FormFieldRenderContext([1 => 3]), 7, [8, 9, 10, 11, 12, 13]);

        self::assertStringContainsString('/FT /Btn', $contents);
        self::assertStringContainsString('/Ff 49152', $contents);
        self::assertStringContainsString('/Kids [8 0 R 11 0 R]', $contents);
        self::assertStringContainsString('/V /express', $contents);
        self::assertCount(6, $objects);
        self::assertStringContainsString('/Parent 7 0 R', $objects[0]->contents);
        self::assertStringContainsString('/AS /express', $objects[3]->contents);
    }

    public function testItRendersAComboBox(): void
    {
        $field = new ComboBoxField(
            'status',
            1,
            10.0,
            20.0,
            80.0,
            12.0,
            ['new' => 'New', 'done' => 'Done'],
            'done',
            'Status',
        );

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7);

        self::assertStringContainsString('/FT /Ch', $contents);
        self::assertStringContainsString('/Ff 131072', $contents);
        self::assertStringContainsString('/Opt [[(new) (New)] [(done) (Done)]]', $contents);
        self::assertStringContainsString('/V (done)', $contents);
    }

    public function testItRendersAMultiselectListBox(): void
    {
        $field = new ListBoxField(
            'skills',
            1,
            10.0,
            20.0,
            80.0,
            40.0,
            ['php' => 'PHP', 'pdf' => 'PDF', 'qa' => 'QA'],
            ['php', 'pdf'],
            'Skills',
        );

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7);

        self::assertStringContainsString('/FT /Ch', $contents);
        self::assertStringContainsString('/Ff 2097152', $contents);
        self::assertStringContainsString('/V [(php) (pdf)]', $contents);
    }

    public function testItRendersAPushButtonWithUriAction(): void
    {
        $field = new PushButtonField(
            'open_docs',
            1,
            10.0,
            20.0,
            90.0,
            18.0,
            'Open docs',
            'Open documentation',
            'https://example.com/docs',
        );

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7);

        self::assertStringContainsString('/FT /Btn', $contents);
        self::assertStringContainsString('/Ff 65536', $contents);
        self::assertStringContainsString('/MK << /CA (Open docs) >>', $contents);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/docs) >>', $contents);
    }

    public function testItRendersASignatureFieldWithAppearanceReference(): void
    {
        $field = new SignatureField(
            'approval_signature',
            1,
            10.0,
            20.0,
            100.0,
            30.0,
            'Approval signature',
        );

        $contents = $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7, [9]);
        $objects = $field->relatedObjects(new FormFieldRenderContext([1 => 3]), 7, [9]);

        self::assertStringContainsString('/FT /Sig', $contents);
        self::assertStringContainsString('/Border [0 0 1]', $contents);
        self::assertStringContainsString('/AP << /N 9 0 R >>', $contents);
        self::assertStringContainsString('/TU (Approval signature)', $contents);
        self::assertCount(1, $objects);
        self::assertSame(9, $objects[0]->objectId);
        self::assertStringContainsString('/Subtype /Form', $objects[0]->contents);
        self::assertStringContainsString('0 0 100 30 re', $objects[0]->contents);
    }

    public function testItRejectsSignatureFieldsWithoutAppearanceObjectIds(): void
    {
        $field = new SignatureField(
            'approval_signature',
            1,
            10.0,
            20.0,
            100.0,
            30.0,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature fields require one appearance object ID.');

        $field->pdfObjectContents(new FormFieldRenderContext([1 => 3]), 7, []);
    }
}
