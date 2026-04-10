<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class CircleAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly ?Color $borderColor = null,
        private readonly ?Color $fillColor = null,
        private readonly ?string $contents = null,
        private readonly ?string $title = null,
        private readonly ?AnnotationBorderStyle $borderStyle = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Circle'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
        ]);

        if ($this->page->getDocument()->getProfile()->requiresPrintableAnnotations()) {
            $dictionary->add('F', self::PRINT_FLAG);
        }

        $this->addStructParentEntry($dictionary);

        if ($this->borderColor !== null) {
            $dictionary->add('C', new ArrayType($this->colorComponents($this->borderColor)));
        }

        if ($this->fillColor !== null) {
            $dictionary->add('IC', new ArrayType($this->colorComponents($this->fillColor)));
        }

        if ($this->contents !== null && $this->contents !== '') {
            $dictionary->add('Contents', new StringType($this->contents));
        }

        if ($this->title !== null && $this->title !== '') {
            $dictionary->add('T', new StringType($this->title));
        }

        if ($this->borderStyle !== null) {
            $dictionary->add('BS', $this->borderStyle->toPdfDictionary());
        }

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        return $dictionary;
    }

    public function getRelatedObjects(): array
    {
        return $this->appearance !== null ? [$this->appearance] : [];
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
