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

final class LineAnnotation extends IndirectObject implements PageAnnotation
{
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

    public function render(): string
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

        if ($this->popup !== null) {
            $dictionary->add('Popup', new ReferenceType($this->popup));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getRelatedObjects(): array
    {
        return $this->popup !== null ? [$this->popup] : [];
    }

    public function withPopup(PopupAnnotation $popup): self
    {
        $this->popup = $popup;

        return $this;
    }

    /**
     * @return list<float>
     */
    private function colorComponents(Color $color): array
    {
        $rendered = preg_split('/\s+/', $color->renderNonStrokingOperator());
        if ($rendered === false) {
            return [];
        }

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
