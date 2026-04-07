<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class CaretAnnotation extends IndirectObject implements PageAnnotation
{
    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly ?string $contents = null,
        private readonly ?string $title = null,
        private readonly string $symbol = 'None',
    ) {
        parent::__construct($id);

        if (!in_array($this->symbol, ['None', 'P'], true)) {
            throw new InvalidArgumentException('Caret annotation symbol must be "None" or "P".');
        }
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Caret'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'Sy' => new NameType($this->symbol),
        ]);

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $dictionary->add('F', self::PRINT_FLAG);
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

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
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
}
