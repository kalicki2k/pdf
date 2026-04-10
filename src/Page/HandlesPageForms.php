<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Action\ButtonAction;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Page\Form\FormFieldLabel;
use Kalle\Pdf\Style\Color;

trait HandlesPageForms
{
    public function addTextField(
        string $name,
        Rect $box,
        ?string $value = null,
        string $baseFont = 'Helvetica',
        int $size = 12,
        bool $multiline = false,
        ?Color $textColor = null,
        ?FormFieldFlags $flags = null,
        ?string $defaultValue = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addTextField(
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
            $fieldLabel,
        );

        return $this;
    }

    public function addCheckbox(
        string $name,
        Position $position,
        float $size,
        bool $checked = false,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addCheckbox($name, $position, $size, $checked, $accessibleName, $fieldLabel);

        return $this;
    }

    public function addRadioButton(
        string $name,
        string $value,
        Position $position,
        float $size,
        bool $checked = false,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addRadioButton($name, $value, $position, $size, $checked, $accessibleName, $fieldLabel);

        return $this;
    }

    /**
     * @param array<string, string> $options
     */
    public function addComboBox(
        string $name,
        Rect $box,
        array $options,
        ?string $value = null,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?FormFieldFlags $flags = null,
        ?string $defaultValue = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addComboBox(
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
            $fieldLabel,
        );

        return $this;
    }

    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @param list<string>|string|null $defaultValue
     */
    public function addListBox(
        string $name,
        Rect $box,
        array $options,
        string | array | null $value = null,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?FormFieldFlags $flags = null,
        string | array | null $defaultValue = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addListBox(
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
            $fieldLabel,
        );

        return $this;
    }

    public function addSignatureField(
        string $name,
        Rect $box,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addSignatureField($name, $box, $accessibleName, $fieldLabel);

        return $this;
    }

    public function addPushButton(
        string $name,
        string $label,
        Rect $box,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?ButtonAction $action = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $this->collaborators->forms()->addPushButton(
            $name,
            $label,
            $box,
            $baseFont,
            $size,
            $textColor,
            $action,
            $accessibleName,
            $fieldLabel,
        );

        return $this;
    }
}
