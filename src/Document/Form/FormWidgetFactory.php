<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Closure;
use InvalidArgumentException;
use Kalle\Pdf\Document\Action\ButtonAction;
use Kalle\Pdf\Document\Annotation\CheckboxAnnotation;
use Kalle\Pdf\Document\Annotation\ComboBoxAnnotation;
use Kalle\Pdf\Document\Annotation\ListBoxAnnotation;
use Kalle\Pdf\Document\Annotation\PushButtonAnnotation;
use Kalle\Pdf\Document\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Document\Annotation\SignatureFieldAnnotation;
use Kalle\Pdf\Document\Annotation\TextFieldAnnotation;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Object\IndirectObject;

/**
 * Builds form widgets so Page can stay focused on the public API surface.
 */
final readonly class FormWidgetFactory
{
    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(): AcroForm $ensureTextFieldAcroForm
     * @param Closure(): AcroForm $ensurePushButtonAcroForm
     * @param Closure(): AcroForm $ensureRadioButtonAcroForm
     * @param Closure(): AcroForm $ensureComboBoxAcroForm
     * @param Closure(): AcroForm $ensureListBoxAcroForm
     * @param Closure(string): FontDefinition $resolveFont
     */
    public function __construct(
        private Page    $page,
        private Closure $nextObjectId,
        private Closure $ensureTextFieldAcroForm,
        private Closure $ensurePushButtonAcroForm,
        private Closure $ensureRadioButtonAcroForm,
        private Closure $ensureComboBoxAcroForm,
        private Closure $ensureListBoxAcroForm,
        private Closure $resolveFont,
    ) {
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
        if ($name === '') {
            throw new InvalidArgumentException('Checkbox name must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Checkbox size must be greater than zero.');
        }

        return new CheckboxAnnotation(
            $this->nextObjectId(),
            $this->page,
            $position->x,
            $position->y,
            $size,
            $size,
            $name,
            $checked,
            new CheckboxAppearanceStream($this->nextObjectId(), $size, $size, false),
            new CheckboxAppearanceStream($this->nextObjectId(), $size, $size, true),
            $accessibleName,
        );
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
        if ($name === '') {
            throw new InvalidArgumentException('Radio button name must not be empty.');
        }

        if ($value === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new InvalidArgumentException('Radio button value may contain only letters, numbers, dots, underscores and hyphens.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Radio button size must be greater than zero.');
        }

        $acroForm = $this->ensureRadioButtonAcroForm();
        $group = $acroForm->getOrCreateRadioGroup($name, $this->nextObjectId());
        $annotation = new RadioButtonWidgetAnnotation(
            $this->nextObjectId(),
            $this->page,
            $group,
            $position->x,
            $position->y,
            $size,
            $value,
            $checked,
            new RadioButtonAppearanceStream($this->nextObjectId(), $size, false),
            new RadioButtonAppearanceStream($this->nextObjectId(), $size, true),
        );

        if ($accessibleName !== null && $accessibleName !== '') {
            $group->withTooltip($name);
        }

        return [$group, $annotation];
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

        [$font, $fontResourceName] = $this->prepareComboBoxAcroFormFont($baseFont);

        return new ComboBoxAnnotation(
            $this->nextObjectId(),
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
                $this->nextObjectId(),
                $box->width,
                $box->height,
                $font,
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

        [$font, $fontResourceName] = $this->prepareListBoxAcroFormFont($baseFont);

        return new ListBoxAnnotation(
            $this->nextObjectId(),
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
                $this->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $fontResourceName,
                $size,
                $this->resolveListBoxAppearanceLines($options, $value),
                $textColor,
                HorizontalAlign::LEFT,
                VerticalAlign::TOP,
            ),
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
        return ($this->nextObjectId)();
    }

    private function ensureTextFieldAcroForm(): AcroForm
    {
        return ($this->ensureTextFieldAcroForm)();
    }

    private function ensurePushButtonAcroForm(): AcroForm
    {
        return ($this->ensurePushButtonAcroForm)();
    }

    private function ensureRadioButtonAcroForm(): AcroForm
    {
        return ($this->ensureRadioButtonAcroForm)();
    }

    private function ensureComboBoxAcroForm(): AcroForm
    {
        return ($this->ensureComboBoxAcroForm)();
    }

    private function ensureListBoxAcroForm(): AcroForm
    {
        return ($this->ensureListBoxAcroForm)();
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function prepareTextFieldAcroFormFont(string $baseFont): array
    {
        $font = ($this->resolveFont)($baseFont);

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
        $font = ($this->resolveFont)($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $this->ensurePushButtonAcroForm()->registerFont($font)];
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function prepareComboBoxAcroFormFont(string $baseFont): array
    {
        $font = ($this->resolveFont)($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $this->ensureComboBoxAcroForm()->registerFont($font)];
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function prepareListBoxAcroFormFont(string $baseFont): array
    {
        $font = ($this->resolveFont)($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $this->ensureListBoxAcroForm()->registerFont($font)];
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
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @return list<string>
     */
    private function resolveListBoxAppearanceLines(array $options, string | array | null $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $label = $options[$value] ?? null;

            return $label === null ? [] : [$label];
        }

        $labels = [];

        foreach ($value as $selectedValue) {
            if (isset($options[$selectedValue])) {
                $labels[] = $options[$selectedValue];
            }
        }

        return $labels;
    }
}
