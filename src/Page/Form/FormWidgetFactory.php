<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use InvalidArgumentException;
use Kalle\Pdf\Action\ButtonAction;
use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
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
    private FormTextWidgetFactory $textWidgets;
    private FormToggleWidgetFactory $toggleWidgets;

    public function __construct(
        private Page $page,
        private FormWidgetFactoryContext $context,
        UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
    ) {
        $this->choiceWidgets = new FormChoiceWidgetFactory($page, $context, $unicodeFontWidthUpdater);
        $this->textWidgets = new FormTextWidgetFactory($page, $context, $unicodeFontWidthUpdater);
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
        return $this->textWidgets->createTextField(
            $name,
            $box,
            $value,
            $baseFont,
            $size,
            $multiline,
            $textColor,
            $flags,
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
        return $this->textWidgets->createPushButton(
            $name,
            $label,
            $box,
            $baseFont,
            $size,
            $textColor,
            $action,
            $accessibleName,
        );
    }

    private function nextObjectId(): int
    {
        return $this->context->nextObjectId();
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

}
