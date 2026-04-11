<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use InvalidArgumentException;
use Kalle\Pdf\Action\ButtonAction;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Annotation\CheckboxAnnotation;
use Kalle\Pdf\Page\Annotation\ComboBoxAnnotation;
use Kalle\Pdf\Page\Annotation\ListBoxAnnotation;
use Kalle\Pdf\Page\Annotation\PushButtonAnnotation;
use Kalle\Pdf\Page\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Page\Annotation\SignatureFieldAnnotation;
use Kalle\Pdf\Page\Annotation\TextFieldAnnotation;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

/**
 * Builds form widgets so Page can stay focused on the public API surface.
 */
final readonly class FormWidgetFactory
{
    private FormChoiceWidgetFactory $choiceWidgets;
    private FormToggleWidgetFactory $toggleWidgets;

    public function __construct(
        private Page $page,
        private FormWidgetFactoryContext $context,
        private UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
    ) {
        $this->choiceWidgets = new FormChoiceWidgetFactory($page, $context, $unicodeFontWidthUpdater);
        $this->toggleWidgets = new FormToggleWidgetFactory($page, $context);
    }

    public function createTextField(
        string $name,
        Rect $box,
        ?string $value,
        string $baseFont,
        int $size,
        bool $multiline,
        ?Color $textColor,
        ?FormFieldFlags $flags,
        ?string $defaultValue,
        ?string $accessibleName,
    ): TextFieldAnnotation {
        if ($name === '') {
            throw new InvalidArgumentException('Text field name must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'Text field');

        if ($size <= 0) {
            throw new InvalidArgumentException('Text field font size must be greater than zero.');
        }

        [$font, $fontResourceName] = $this->prepareTextFieldAcroFormFont($baseFont);

        return new TextFieldAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $value,
            $fontResourceName,
            $size,
            $multiline,
            $flags,
            $textColor,
            $defaultValue,
            $accessibleName,
            new FormFieldTextAppearanceStream(
                $this->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $this->unicodeFontWidthUpdater,
                $fontResourceName,
                $size,
                $this->resolveTextFieldAppearanceLines($value, $multiline),
                $textColor,
                HorizontalAlign::LEFT,
                $multiline ? VerticalAlign::TOP : VerticalAlign::MIDDLE,
            ),
        );
    }

    public function createCheckbox(
        string $name,
        Position $position,
        float $size,
        bool $checked,
        ?string $accessibleName,
    ): CheckboxAnnotation {
        return $this->toggleWidgets->createCheckbox($name, $position, $size, $checked, $accessibleName);
    }

    /**
     * @return array{0: RadioButtonField, 1: RadioButtonWidgetAnnotation}
     */
    public function createRadioButton(
        string $name,
        string $value,
        Position $position,
        float $size,
        bool $checked,
        ?string $accessibleName,
    ): array {
        return $this->toggleWidgets->createRadioButton($name, $value, $position, $size, $checked, $accessibleName);
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
        return $this->choiceWidgets->createComboBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $accessibleName,
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
        return $this->choiceWidgets->createListBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $accessibleName,
        );
    }

    public function createSignatureField(string $name, Rect $box, ?string $accessibleName): SignatureFieldAnnotation
    {
        if ($name === '') {
            throw new InvalidArgumentException('Signature field name must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'Signature field');

        return new SignatureFieldAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $accessibleName,
            new FormFieldSignatureAppearanceStream(
                $this->nextObjectId(),
                $box->width,
                $box->height,
            ),
        );
    }

    public function createPushButton(
        string $name,
        string $label,
        Rect $box,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?ButtonAction $action,
        ?string $accessibleName,
    ): PushButtonAnnotation {
        if ($name === '') {
            throw new InvalidArgumentException('Push button name must not be empty.');
        }

        if ($label === '') {
            throw new InvalidArgumentException('Push button label must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'Push button');

        if ($size <= 0) {
            throw new InvalidArgumentException('Push button font size must be greater than zero.');
        }

        [$font, $fontResourceName] = $this->preparePushButtonAcroFormFont($baseFont);

        return new PushButtonAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $label,
            $fontResourceName,
            $size,
            $textColor,
            $action,
            $accessibleName,
            new FormFieldTextAppearanceStream(
                $this->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $this->unicodeFontWidthUpdater,
                $fontResourceName,
                $size,
                [$label],
                $textColor,
                HorizontalAlign::CENTER,
                VerticalAlign::MIDDLE,
            ),
        );
    }

    private function nextObjectId(): int
    {
        return $this->context->nextObjectId();
    }

    private function ensureTextFieldAcroForm(): AcroForm
    {
        return $this->context->ensureTextFieldAcroForm();
    }

    private function ensurePushButtonAcroForm(): AcroForm
    {
        return $this->context->ensurePushButtonAcroForm();
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function prepareTextFieldAcroFormFont(string $baseFont): array
    {
        $font = $this->context->resolveFont($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $this->ensureTextFieldAcroForm()->registerFont($font)];
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function preparePushButtonAcroFormFont(string $baseFont): array
    {
        $font = $this->context->resolveFont($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $this->ensurePushButtonAcroForm()->registerFont($font)];
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
     * @return list<string>
     */
    private function resolveTextFieldAppearanceLines(?string $value, bool $multiline): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!$multiline) {
            return [$value];
        }

        $lines = preg_split('/\R/u', $value) ?: [];

        return $lines;
    }

}
