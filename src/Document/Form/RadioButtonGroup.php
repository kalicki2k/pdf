<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class RadioButtonGroup extends FormField
{
    /**
     * @param list<RadioButtonChoice> $choices
     */
    public function __construct(
        string $name,
        public array $choices = [],
        ?string $alternativeName = null,
    ) {
        parent::__construct($name, $alternativeName);
    }

    public function withChoice(RadioButtonChoice $choice): self
    {
        $choices = [];

        foreach ($this->choices as $existingChoice) {
            if ($existingChoice->exportValue === $choice->exportValue) {
                throw new InvalidArgumentException(sprintf(
                    'Radio button export value "%s" is already registered for group "%s".',
                    $choice->exportValue,
                    $this->name,
                ));
            }

            $choices[] = $choice->checked && $existingChoice->checked
                ? new RadioButtonChoice(
                    pageNumber: $existingChoice->pageNumber,
                    x: $existingChoice->x,
                    y: $existingChoice->y,
                    size: $existingChoice->size,
                    exportValue: $existingChoice->exportValue,
                    checked: false,
                    alternativeName: $existingChoice->alternativeName,
                )
                : $existingChoice;
        }

        return new self(
            $this->name,
            [...$choices, $choice],
            $this->alternativeName,
        );
    }

    public function relatedObjectCount(): int
    {
        return count($this->choices) * 3;
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        if (count($relatedObjectIds) !== $this->relatedObjectCount()) {
            throw new InvalidArgumentException('Radio button groups require widget and appearance object IDs for every choice.');
        }

        $widgetObjectIds = [];

        for ($index = 0; $index < count($this->choices); $index++) {
            $widgetObjectIds[] = $relatedObjectIds[$index * 3];
        }

        $entries = [
            '/FT /Btn',
            '/T ' . $this->pdfString($this->name),
            '/Ff 49152',
            '/Kids [' . implode(' ', array_map(
                static fn (int $objectId): string => $objectId . ' 0 R',
                $widgetObjectIds,
            )) . ']',
        ];

        $selectedValue = $this->selectedValue();

        if ($selectedValue !== null) {
            $entries[] = '/V /' . $this->pdfName($selectedValue);
        }

        if ($this->alternativeName !== null) {
            $entries[] = '/TU ' . $this->pdfString($this->alternativeName);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== $this->relatedObjectCount()) {
            throw new InvalidArgumentException('Radio button groups require widget and appearance object IDs for every choice.');
        }

        $objects = [];

        foreach ($this->choices as $index => $choice) {
            $widgetObjectId = $relatedObjectIds[$index * 3];
            $offAppearanceObjectId = $relatedObjectIds[$index * 3 + 1];
            $onAppearanceObjectId = $relatedObjectIds[$index * 3 + 2];
            $state = $choice->checked ? $this->pdfName($choice->exportValue) : 'Off';

            $widgetEntries = [
                '/Type /Annot',
                '/Subtype /Widget',
                '/Rect ['
                    . $this->formatNumber($choice->x)
                    . ' '
                    . $this->formatNumber($choice->y)
                    . ' '
                    . $this->formatNumber($choice->x + $choice->size)
                    . ' '
                    . $this->formatNumber($choice->y + $choice->size)
                    . ']',
                '/Border [0 0 0]',
                '/P ' . $context->pageObjectId($choice->pageNumber) . ' 0 R',
                '/Parent ' . $fieldObjectId . ' 0 R',
                '/AS /' . $state,
            ];

            $structParentId = $context->structParentId($widgetObjectId);

            if ($structParentId !== null) {
                $widgetEntries[] = '/StructParent ' . $structParentId;
            }

            if ($choice->alternativeName !== null) {
                $widgetEntries[] = '/TU ' . $this->pdfString($choice->alternativeName);
            }

            $widgetEntries[] = '/AP << /N << /Off ' . $offAppearanceObjectId . ' 0 R /'
                . $this->pdfName($choice->exportValue)
                . ' ' . $onAppearanceObjectId . ' 0 R >> >>';

            $objects[] = IndirectObject::plain($widgetObjectId, '<< ' . implode(' ', $widgetEntries) . ' >>');
            $objects[] = IndirectObject::stream(
                $offAppearanceObjectId,
                $this->appearanceStreamDictionaryContents($choice->size),
                $this->appearanceStreamContents($choice->size, false),
            );
            $objects[] = IndirectObject::stream(
                $onAppearanceObjectId,
                $this->appearanceStreamDictionaryContents($choice->size),
                $this->appearanceStreamContents($choice->size, true),
            );
        }

        return $objects;
    }

    public function pageAnnotationObjectIds(int $fieldObjectId, array $relatedObjectIds = []): array
    {
        if (count($relatedObjectIds) !== $this->relatedObjectCount()) {
            throw new InvalidArgumentException('Radio button groups require widget and appearance object IDs for every choice.');
        }

        $pageAnnotationObjectIds = [];

        foreach ($this->choices as $index => $choice) {
            $pageAnnotationObjectIds[$choice->pageNumber] ??= [];
            $pageAnnotationObjectIds[$choice->pageNumber][] = $relatedObjectIds[$index * 3];
        }

        return $pageAnnotationObjectIds;
    }

    private function selectedValue(): ?string
    {
        foreach ($this->choices as $choice) {
            if ($choice->checked) {
                return $choice->exportValue;
            }
        }

        return null;
    }

    private function appearanceStreamDictionaryContents(float $size): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($size)
            . ' '
            . $this->formatNumber($size)
            . '] /Resources << >> /Length 0 >>';
    }

    private function appearanceStreamContents(float $size, bool $checked): string
    {
        $lines = [
            '1 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($size) . ' ' . $this->formatNumber($size) . ' re',
            'B',
        ];

        if ($checked) {
            $lines[] = '0 g';
            $innerSize = max($size * 0.4, 1.0);
            $innerOffset = ($size - $innerSize) / 2;
            $lines[] = $this->formatNumber($innerOffset)
                . ' '
                . $this->formatNumber($innerOffset)
                . ' '
                . $this->formatNumber($innerSize)
                . ' '
                . $this->formatNumber($innerSize)
                . ' re';
            $lines[] = 'f';
        }

        return implode("\n", $lines);
    }
}
