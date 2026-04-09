<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class InkAnnotation extends IndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    private const int PRINT_FLAG = 4;

    private ?TextAnnotationAppearanceStream $appearance = null;

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly array $paths,
        private readonly ?Color $color = null,
        private readonly ?string $contents = null,
        private readonly ?string $title = null,
    ) {
        parent::__construct($id);

        if ($paths === []) {
            throw new InvalidArgumentException('Ink annotation requires at least one path.');
        }
    }

    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        $inkList = [];

        foreach ($this->paths as $path) {
            $pathValues = [];

            foreach ($path as [$x, $y]) {
                $pathValues[] = $x;
                $pathValues[] = $y;
            }

            $inkList[] = new ArrayType($pathValues);
        }

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Ink'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'InkList' => new ArrayType($inkList),
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

        if ($this->appearance !== null) {
            $dictionary->add('AP', new DictionaryType([
                'N' => new ReferenceType($this->appearance),
            ]));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render($encryptor) . PHP_EOL
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
