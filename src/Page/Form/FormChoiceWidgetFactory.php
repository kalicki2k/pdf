<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Annotation\ComboBoxAnnotation;
use Kalle\Pdf\Page\Annotation\ListBoxAnnotation;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class FormChoiceWidgetFactory
{
    public function __construct(
        private Page $page,
        private FormWidgetFactoryContext $context,
        private UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
    ) {
    }

    /**
     * @param array<string, string> $options
     */
    public function createComboBox(
        string $name,
        Rect $box,
        array $options,
        ?string $value,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?FormFieldFlags $flags,
        ?string $defaultValue,
        ?string $accessibleName,
    ): ComboBoxAnnotation {
        if ($name === '') {
            throw new InvalidArgumentException('Combo box name must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'Combo box');

        if ($size <= 0) {
            throw new InvalidArgumentException('Combo box font size must be greater than zero.');
        }

        $this->assertOptionsAreNotEmpty($options, 'Combo box');

        if ($value !== null && !array_key_exists($value, $options)) {
            throw new InvalidArgumentException('Combo box value must reference one of the available options.');
        }

        if ($defaultValue !== null && !array_key_exists($defaultValue, $options)) {
            throw new InvalidArgumentException('Combo box default value must reference one of the available options.');
        }

        [$font, $fontResourceName] = $this->prepareAcroFormFont($baseFont, $this->context->ensureComboBoxAcroForm());

        return new ComboBoxAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $options,
            $value,
            $fontResourceName,
            $size,
            $flags,
            $textColor,
            $defaultValue,
            $accessibleName,
            new FormFieldTextAppearanceStream(
                $this->context->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $this->unicodeFontWidthUpdater,
                $fontResourceName,
                $size,
                $this->resolveComboBoxAppearanceLines($options, $value),
                $textColor,
                HorizontalAlign::LEFT,
                VerticalAlign::MIDDLE,
                true,
            ),
        );
    }

    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @param list<string>|string|null $defaultValue
     */
    public function createListBox(
        string $name,
        Rect $box,
        array $options,
        string | array | null $value,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?FormFieldFlags $flags,
        string | array | null $defaultValue,
        ?string $accessibleName,
    ): ListBoxAnnotation {
        if ($name === '') {
            throw new InvalidArgumentException('List box name must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'List box');

        if ($size <= 0) {
            throw new InvalidArgumentException('List box font size must be greater than zero.');
        }

        $this->assertOptionsAreNotEmpty($options, 'List box');
        $this->assertSelectionExists($value, $options, 'List box value must reference one of the available options.');
        $this->assertSelectionExists($defaultValue, $options, 'List box default value must reference one of the available options.');

        [$font, $fontResourceName] = $this->prepareAcroFormFont($baseFont, $this->context->ensureListBoxAcroForm());

        return new ListBoxAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $options,
            $value,
            $fontResourceName,
            $size,
            $flags,
            $textColor,
            $defaultValue,
            $accessibleName,
            new FormFieldListBoxAppearanceStream(
                $this->context->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $this->unicodeFontWidthUpdater,
                $fontResourceName,
                $size,
                $options,
                $this->resolveSelectedValues($value),
                $textColor,
            ),
        );
    }

    private function assertRectHasPositiveDimensions(Rect $box, string $subject): void
    {
        if ($box->width <= 0) {
            throw new InvalidArgumentException("$subject width must be greater than zero.");
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException("$subject height must be greater than zero.");
        }
    }

    /**
     * @param array<string, string> $options
     */
    private function assertOptionsAreNotEmpty(array $options, string $subject): void
    {
        if ($options === []) {
            throw new InvalidArgumentException("$subject options must not be empty.");
        }

        foreach ($options as $exportValue => $label) {
            if ($exportValue === '') {
                throw new InvalidArgumentException("$subject option values must not be empty.");
            }

            if ($label === '') {
                throw new InvalidArgumentException("$subject option labels must not be empty.");
            }
        }
    }

    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     */
    private function assertSelectionExists(string | array | null $value, array $options, string $message): void
    {
        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            if (!array_key_exists($value, $options)) {
                throw new InvalidArgumentException($message);
            }

            return;
        }

        foreach ($value as $selectedValue) {
            if (!array_key_exists($selectedValue, $options)) {
                throw new InvalidArgumentException($message);
            }
        }
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function prepareAcroFormFont(string $baseFont, AcroForm $acroForm): array
    {
        $font = $this->context->resolveFont($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $acroForm->registerFont($font)];
    }

    /**
     * @param array<string, string> $options
     * @return list<string>
     */
    private function resolveComboBoxAppearanceLines(array $options, ?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $label = $options[$value] ?? null;

        return $label === null ? [] : [$label];
    }

    /**
     * @param list<string>|string|null $value
     * @return list<string>
     */
    private function resolveSelectedValues(string | array | null $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        return $value;
    }
}
