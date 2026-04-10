<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Form;

use Kalle\Pdf\Internal\Action\ButtonAction;
use Kalle\Pdf\Internal\Action\SetOcgStateAction;
use Kalle\Pdf\Internal\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Page\Annotation\PageAnnotation;
use Kalle\Pdf\Internal\Page\Annotation\PageAnnotations;
use Kalle\Pdf\Internal\Page\Annotation\StructParentAwareAnnotation;
use Kalle\Pdf\Internal\Page\Form\FormFieldFlags;
use Kalle\Pdf\Internal\Page\Form\FormFieldLabel;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructureTag;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Text\TextOptions;

/**
 * @internal Coordinates form widgets and accessibility bindings for a page.
 */
final class PageForms
{
    private ?FormWidgetFactory $factory = null;
    private readonly FormWidgetFactoryContext $factoryContext;

    public function __construct(
        private readonly Page $page,
        private readonly PageAnnotations $pageAnnotations,
        PageFonts $pageFonts,
    ) {
        $this->factoryContext = FormWidgetFactoryContext::forPage($page, $pageFonts);
    }

    public static function forPage(Page $page, PageAnnotations $pageAnnotations, PageFonts $pageFonts): self
    {
        return new self($page, $pageAnnotations, $pageFonts);
    }

    public function addTextField(
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
        ?FormFieldLabel $fieldLabel,
    ): void {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->page->getDocument()->ensureTextFieldAcroForm();
        $annotation = $this->formWidgetFactory()->createTextField(
            $name,
            $box,
            $value,
            $baseFont,
            $size,
            $multiline,
            $textColor,
            $flags,
            $defaultValue,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations->add($annotation);
    }

    public function addCheckbox(
        string $name,
        Position $position,
        float $size,
        bool $checked,
        ?string $accessibleName,
        ?FormFieldLabel $fieldLabel,
    ): void {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->page->getDocument()->ensureCheckboxAcroForm();
        $annotation = $this->formWidgetFactory()->createCheckbox($name, $position, $size, $checked, $resolvedAccessibleName);

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations->add($annotation);
    }

    public function addRadioButton(
        string $name,
        string $value,
        Position $position,
        float $size,
        bool $checked,
        ?string $accessibleName,
        ?FormFieldLabel $fieldLabel,
    ): void {
        $resolvedAccessibleName = $this->resolveRadioButtonAccessibleName($value, $accessibleName, $fieldLabel);
        [$group, $annotation] = $this->formWidgetFactory()->createRadioButton($name, $value, $position, $size, $checked, $resolvedAccessibleName);
        $groupAccessibleName = $this->resolveRadioButtonGroupAccessibleName($name);

        if ($groupAccessibleName !== null) {
            $group->withTooltip($groupAccessibleName);
        }

        $group->addWidget($annotation, $value, $checked);
        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $this->pageAnnotations->add($annotation);
    }

    /**
     * @param array<string, string> $options
     */
    public function addComboBox(
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
        ?FormFieldLabel $fieldLabel,
    ): void {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->page->getDocument()->ensureComboBoxAcroForm();
        $annotation = $this->formWidgetFactory()->createComboBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations->add($annotation);
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
        string | array | null $value,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?FormFieldFlags $flags,
        string | array | null $defaultValue,
        ?string $accessibleName,
        ?FormFieldLabel $fieldLabel,
    ): void {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->page->getDocument()->ensureListBoxAcroForm();
        $annotation = $this->formWidgetFactory()->createListBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations->add($annotation);
    }

    public function addSignatureField(
        string $name,
        Rect $box,
        ?string $accessibleName,
        ?FormFieldLabel $fieldLabel,
    ): void {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->page->getDocument()->ensureSignatureFieldAcroForm();
        $annotation = $this->formWidgetFactory()->createSignatureField($name, $box, $resolvedAccessibleName);

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations->add($annotation);
    }

    public function addPushButton(
        string $name,
        string $label,
        Rect $box,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?ButtonAction $action,
        ?string $accessibleName,
        ?FormFieldLabel $fieldLabel,
    ): void {
        if ($action instanceof SetOcgStateAction) {
            $this->page->getDocument()->assertAllowsOptionalContentGroups();
        }

        $resolvedAccessibleName = $this->resolvePushButtonAccessibleName($name, $label, $accessibleName, $fieldLabel);
        $acroForm = $this->page->getDocument()->ensurePushButtonAcroForm();
        $annotation = $this->formWidgetFactory()->createPushButton(
            $name,
            $label,
            $box,
            $baseFont,
            $size,
            $textColor,
            $action,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations->add($annotation);
    }

    private function formWidgetFactory(): FormWidgetFactory
    {
        return $this->factory ??= new FormWidgetFactory(
            $this->page,
            $this->factoryContext,
            new UnicodeFontWidthUpdater(),
        );
    }

    private function bindAccessibleFormField(
        IndirectObject & PageAnnotation & StructParentAwareAnnotation $annotation,
        ?string $accessibleName,
        bool $hasVisibleLabel = false,
    ): ?StructElem {
        $profile = $this->page->getDocument()->getProfile();

        if (!$profile->requiresTaggedFormFields()) {
            return null;
        }

        $labelParentStructElem = null;

        if ($hasVisibleLabel) {
            $labelParentStructElem = $this->page->getDocument()->createStructElem(StructureTag::Division);
            $labelParentStructElem->setPage($this->page);
        }

        $formStructElem = $this->page->getDocument()->createStructElem(
            StructureTag::Form,
            parent: $labelParentStructElem,
        );
        $formStructElem->setPage($this->page);

        $structParentId = $this->page->getDocument()->getNextStructParentId();
        $annotation->withStructParent($structParentId);
        $formStructElem->addObjectReference($annotation, $this->page);

        if ($profile->requiresFormFieldAlternativeDescriptions() && $accessibleName !== null && $accessibleName !== '') {
            $formStructElem->setAltText($accessibleName);
        }

        $this->page->getDocument()->registerObjectStructElem($structParentId, $formStructElem);

        return $labelParentStructElem;
    }

    private function renderFormFieldLabel(?FormFieldLabel $fieldLabel, ?StructElem $formStructElem): void
    {
        if ($fieldLabel === null) {
            return;
        }

        $this->page->addText(
            $fieldLabel->text,
            $fieldLabel->position,
            $fieldLabel->fontName,
            $fieldLabel->size,
            new TextOptions(
                structureTag: $formStructElem !== null ? StructureTag::Paragraph : null,
                parentStructElem: $formStructElem,
                color: $fieldLabel->color,
            ),
        );
    }

    private function resolveFormFieldAccessibleName(string $name, ?string $accessibleName, ?FormFieldLabel $fieldLabel = null): ?string
    {
        if ($accessibleName !== null && $accessibleName !== '') {
            return $accessibleName;
        }

        if ($this->page->getDocument()->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            return $fieldLabel !== null ? $fieldLabel->text : $name;
        }

        return null;
    }

    private function resolvePushButtonAccessibleName(string $name, string $label, ?string $accessibleName, ?FormFieldLabel $fieldLabel = null): ?string
    {
        if ($accessibleName !== null && $accessibleName !== '') {
            return $accessibleName;
        }

        if ($this->page->getDocument()->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            if ($fieldLabel !== null) {
                return $fieldLabel->text;
            }

            return $label !== '' ? $label : $name;
        }

        return null;
    }

    private function resolveRadioButtonAccessibleName(string $value, ?string $accessibleName, ?FormFieldLabel $fieldLabel = null): ?string
    {
        if ($accessibleName !== null && $accessibleName !== '') {
            return $accessibleName;
        }

        if ($this->page->getDocument()->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            return $fieldLabel !== null ? $fieldLabel->text : $value;
        }

        return null;
    }

    private function resolveRadioButtonGroupAccessibleName(string $name): ?string
    {
        if ($this->page->getDocument()->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            return $name;
        }

        return null;
    }
}
