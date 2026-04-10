<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\PdfType\StringType;
use Kalle\Pdf\Style\Color;

final class LineAnnotation extends DictionaryIndirectObject implements AppearanceStreamAwareAnnotation, PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;
    private ?PopupAnnotation $popup = null;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x1,
        private readonly float $y1,
        private readonly float $x2,
        private readonly float $y2,
        private readonly ?Color $color = null,
        private readonly ?string $contents = null,
        private readonly ?string $title = null,
        private readonly ?LineEndingStyle $startStyle = null,
        private readonly ?LineEndingStyle $endStyle = null,
        private readonly ?string $subject = null,
        private readonly ?AnnotationBorderStyle $borderStyle = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $minX = min($this->x1, $this->x2);
        $minY = min($this->y1, $this->y2);
        $maxX = max($this->x1, $this->x2);
        $maxY = max($this->y1, $this->y2);

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Line'),
            'Rect' => new ArrayType([$minX, $minY, $maxX, $maxY]),
            'P' => new ReferenceType($this->page),
            'L' => new ArrayType([$this->x1, $this->y1, $this->x2, $this->y2]),
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
