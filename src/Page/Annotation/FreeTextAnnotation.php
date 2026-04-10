<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\PdfType\StringType;
use Kalle\Pdf\Style\Color;

final class FreeTextAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
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
        private readonly string $contents,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly ?Color $textColor = null,
        private readonly ?Color $borderColor = null,
        private readonly ?Color $fillColor = null,
        private readonly ?string $title = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $defaultAppearance = sprintf(
            '/%s %d Tf %s',
            $this->fontResourceName,
            $this->fontSize,
            $this->textColor?->renderNonStrokingOperator() ?? '0 g',
        );

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('FreeText'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'Contents' => new StringType($this->contents),
            'DA' => new StringType($defaultAppearance),
        ]);

        if ($this->page->getDocument()->getProfile()->requiresPrintableAnnotations()) {
            $dictionary->add('F', self::PRINT_FLAG);
        }

        $this->addStructParentEntry($dictionary);

        if ($this->title !== null && $this->title !== '') {
            $dictionary->add('T', new StringType($this->title));
        }

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        if ($this->borderColor !== null) {
            $dictionary->add('C', new ArrayType($this->colorComponents($this->borderColor)));
        }

        if ($this->fillColor !== null) {
            $dictionary->add('IC', new ArrayType($this->colorComponents($this->fillColor)));
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
