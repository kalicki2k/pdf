<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Action\UriAction;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\RawType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class LinkAnnotation extends IndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    private const int PRINT_FLAG = 4;
    private ?string $contents = null;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly LinkTarget $target,
    ) {
        parent::__construct($id);
    }

    public function withContents(string $contents): self
    {
        $this->contents = $contents;

        return $this;
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Link'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'Border' => new ArrayType([0, 0, 0]),
            'P' => new ReferenceType($this->page),
        ]);

        if ($this->page->getDocument()->getProfile()->requiresPrintableAnnotations()) {
            $dictionary->add('F', self::PRINT_FLAG);
        }

        $this->addStructParentEntry($dictionary);

        if ($this->contents !== null && $this->contents !== '') {
            $dictionary->add('Contents', new StringType($this->contents));
        }

        if ($this->target->isNamedDestination()) {
            $dictionary->add('Dest', new NameType($this->target->namedDestinationValue()));
        } elseif ($this->target->isPage()) {
            $dictionary->add('Dest', new ArrayType([
                new ReferenceType($this->target->pageValue()),
                new NameType('Fit'),
            ]));
        } elseif ($this->target->isPosition()) {
            $dictionary->add('Dest', new ArrayType([
                new ReferenceType($this->target->pageValue()),
                new NameType('XYZ'),
                $this->target->xValue(),
                $this->target->yValue(),
                new RawType('null'),
            ]));
        } else {
            $dictionary->add('A', new UriAction($this->target->externalUrlValue())->toPdfDictionary());
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getRelatedObjects(): array
    {
        return [];
    }
}
