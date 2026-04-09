<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class PolyLineAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;
    private ?PopupAnnotation $popup = null;

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly array $vertices,
        private readonly ?Color $color = null,
        private readonly ?string $contents = null,
        private readonly ?string $title = null,
        private readonly ?LineEndingStyle $startStyle = null,
        private readonly ?LineEndingStyle $endStyle = null,
        private readonly ?string $subject = null,
        private readonly ?AnnotationBorderStyle $borderStyle = null,
    ) {
        parent::__construct($id);

        if (count($vertices) < 2) {
            throw new InvalidArgumentException('PolyLine annotation requires at least two vertices.');
        }
    }

    protected function dictionary(): DictionaryType
    {
        $xValues = array_map(static fn (array $vertex): float => $vertex[0], $this->vertices);
        $yValues = array_map(static fn (array $vertex): float => $vertex[1], $this->vertices);
        assert($xValues !== []);
        assert($yValues !== []);
        $vertexValues = [];

        foreach ($this->vertices as [$x, $y]) {
            $vertexValues[] = $x;
            $vertexValues[] = $y;
        }

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('PolyLine'),
            'Rect' => new ArrayType([
                min($xValues),
                min($yValues),
                max($xValues),
                max($yValues),
            ]),
            'P' => new ReferenceType($this->page),
            'Vertices' => new ArrayType($vertexValues),
        ]);

        if ($this->page->getDocument()->getProfile()->requiresPrintableAnnotations()) {
            $dictionary->add('F', self::PRINT_FLAG);
        }

        $this->addStructParentEntry($dictionary);

        if ($this->color !== null) {
            $dictionary->add('C', new ArrayType($this->colorComponents($this->color)));
        }

        if ($this->contents !== null && $this->contents !== '') {
            $dictionary->add('Contents', new StringType($this->contents));
        }

        if ($this->title !== null && $this->title !== '') {
            $dictionary->add('T', new StringType($this->title));
        }

        if ($this->subject !== null && $this->subject !== '') {
            $dictionary->add('Subj', new StringType($this->subject));
        }

        if ($this->borderStyle !== null) {
            $dictionary->add('BS', $this->borderStyle->toPdfDictionary());
        }

        if ($this->startStyle !== null || $this->endStyle !== null) {
            $dictionary->add('LE', new ArrayType([
                new NameType(($this->startStyle ?? LineEndingStyle::NONE)->value),
                new NameType(($this->endStyle ?? LineEndingStyle::NONE)->value),
            ]));
        }

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        if ($this->popup !== null) {
            $dictionary->add('Popup', new ReferenceType($this->popup));
        }

        return $dictionary;
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
