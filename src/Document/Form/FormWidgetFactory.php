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

/**
 * Builds form widgets so Page can stay focused on the public API surface.
 */
final readonly class FormWidgetFactory
{
    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(): AcroForm $ensureAcroForm
     * @param Closure(): AcroForm $ensureTextFieldAcroForm
     * @param Closure(string): FontDefinition $resolveFont
     */
    public function __construct(
        private Page    $page,
        private Closure $nextObjectId,
        private Closure $ensureAcroForm,
        private Closure $ensureTextFieldAcroForm,
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

        $fontResourceName = $this->registerTextFieldAcroFormFont($baseFont);

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

        $acroForm = $this->ensureAcroForm();
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

        $fontResourceName = $this->registerAcroFormFont($baseFont);

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

        $fontResourceName = $this->registerAcroFormFont($baseFont);

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
        );
    }

    public function createSignatureField(string $name, Rect $box): SignatureFieldAnnotation
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

        $fontResourceName = $this->registerAcroFormFont($baseFont);

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
        );
    }

    private function nextObjectId(): int
    {
        return ($this->nextObjectId)();
    }

    private function ensureAcroForm(): AcroForm
    {
        return ($this->ensureAcroForm)();
    }

    private function ensureTextFieldAcroForm(): AcroForm
    {
        return ($this->ensureTextFieldAcroForm)();
    }

    private function registerAcroFormFont(string $baseFont): string
    {
        $font = ($this->resolveFont)($baseFont);

        return $this->ensureAcroForm()->registerFont($font);
    }

    private function registerTextFieldAcroFormFont(string $baseFont): string
    {
        $font = ($this->resolveFont)($baseFont);

        return $this->ensureTextFieldAcroForm()->registerFont($font);
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
}
