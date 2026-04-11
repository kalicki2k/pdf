<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\RadioButtonField;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Page\Annotation\CheckboxAnnotation;
use Kalle\Pdf\Page\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Page\Page;

final readonly class FormToggleWidgetFactory
{
    public function __construct(
        private Page $page,
        private FormWidgetFactoryContext $context,
    ) {
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
            $this->context->nextObjectId(),
            $this->page,
            $position->x,
            $position->y,
            $size,
            $size,
            $name,
            $checked,
            new CheckboxAppearanceStream($this->context->nextObjectId(), $size, $size, false),
            new CheckboxAppearanceStream($this->context->nextObjectId(), $size, $size, true),
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
        $group = $acroForm->getOrCreateRadioGroup($name, $this->context->nextObjectId());
        $annotation = new RadioButtonWidgetAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $group,
            $position->x,
            $position->y,
            $size,
            $value,
            $checked,
            new RadioButtonAppearanceStream($this->context->nextObjectId(), $size, false),
            new RadioButtonAppearanceStream($this->context->nextObjectId(), $size, true),
        );

        if ($accessibleName !== null && $accessibleName !== '') {
            $group->withTooltip($name);
        }

        return [$group, $annotation];
    }

    private function ensureRadioButtonAcroForm(): AcroForm
    {
        return $this->context->ensureRadioButtonAcroForm();
    }
}
