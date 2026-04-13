<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use InvalidArgumentException;
use Override;

abstract readonly class WidgetFormField extends FormField
{
    public function __construct(
        string $name,
        public int $pageNumber,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        ?string $alternativeName = null,
    ) {
        parent::__construct($name, $alternativeName);

        if ($this->pageNumber < 1) {
            throw new InvalidArgumentException('Widget form field page number must be greater than zero.');
        }

        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Widget form field width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Widget form field height must be greater than zero.');
        }
    }

    public function pageNumber(): ?int
    {
        return $this->pageNumber;
    }

    /**
     * @return list<string>
     */
    protected function widgetDictionaryEntries(FormFieldRenderContext $context, int $annotationObjectId): array
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Widget',
            '/Rect ['
                . $this->formatNumber($this->x)
                . ' '
                . $this->formatNumber($this->y)
                . ' '
                . $this->formatNumber($this->x + $this->width)
                . ' '
                . $this->formatNumber($this->y + $this->height)
                . ']',
            '/P ' . $context->pageObjectId($this->pageNumber) . ' 0 R',
            '/T ' . $this->pdfString($this->name),
            '/F 4',
        ];

        if ($this->alternativeName !== null) {
            $entries[] = '/TU ' . $this->pdfString($this->alternativeName);
        }

        $structParentId = $context->structParentId($annotationObjectId);

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        return $entries;
    }

    #[Override]
    public function pageAnnotationObjectIds(int $fieldObjectId, array $relatedObjectIds = []): array
    {
        return [
            $this->pageNumber => [$fieldObjectId],
        ];
    }
}
