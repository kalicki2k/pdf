<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class StrikeOutAnnotation extends IndirectObject implements PageAnnotation
{
    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;
    private ?PopupAnnotation $popup = null;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly ?Color $color = null,
        private readonly ?string $contents = null,
        private readonly ?string $title = null,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('StrikeOut'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'QuadPoints' => new ArrayType([
                $this->x,
                $this->y + $this->height,
                $this->x + $this->width,
                $this->y + $this->height,
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y,
            ]),
        ]);

        if ($this->page->getDocument()->getProfile()->requiresPrintableAnnotations()) {
            $dictionary->add('F', self::PRINT_FLAG);
        }

        if ($this->color !== null) {
            $dictionary->add('C', new ArrayType($this->colorComponents($this->color)));
        }

        if ($this->contents !== null && $this->contents !== '') {
            $dictionary->add('Contents', new StringType($this->contents));
        }

        if ($this->title !== null && $this->title !== '') {
            $dictionary->add('T', new StringType($this->title));
        }

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        if ($this->popup !== null) {
            $dictionary->add('Popup', new ReferenceType($this->popup));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getRelatedObjects(): array
    {
        return array_values(array_filter([
            $this->popup,
            $this->appearance,
        ]));
    }

    public function withPopup(PopupAnnotation $popup): self
    {
        $this->popup = $popup;

        return $this;
    }

    public function withAppearance(TextAnnotationAppearanceStream $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }

    /**
     * @return list<float>
     */
    private function colorComponents(Color $color): array
    {
        $rendered = preg_split('/\s+/', $color->renderNonStrokingOperator());
        assert(is_array($rendered));

        $components = [];

        foreach ($rendered as $token) {
            if ($token === 'g' || $token === 'rg' || $token === 'k' || $token === '') {
                continue;
            }

            $components[] = (float) $token;
        }

        return $components;
    }
}
