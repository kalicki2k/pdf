<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Form\FormFieldTextAppearanceStream;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class TextFieldAnnotation extends IndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly string $name,
        private readonly ?string $value,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly bool $multiline = false,
        private readonly ?FormFieldFlags $flags = null,
        private readonly ?Color $textColor = null,
        private readonly ?string $defaultValue = null,
        private readonly ?string $tooltip = null,
        private readonly ?FormFieldTextAppearanceStream $appearance = null,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        $defaultAppearance = sprintf(
            '/%s %d Tf %s',
            $this->fontResourceName,
            $this->fontSize,
            $this->textColor?->renderNonStrokingOperator() ?? '0 g',
        );

        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('Widget'),
            'FT' => new NameType('Tx'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'Border' => new ArrayType([0, 0, 1]),
            'P' => new ReferenceType($this->page),
            'T' => new StringType($this->name),
            'DA' => new StringType($defaultAppearance),
        ]);

        $this->addStructParentEntry($dictionary);

        if ($this->tooltip !== null && $this->tooltip !== '') {
            $dictionary->add('TU', new StringType($this->tooltip));
        }

        $fieldFlags = ($this->flags ?? new FormFieldFlags())->toPdfFlags($this->multiline);

        if ($fieldFlags > 0) {
            $dictionary->add('Ff', $fieldFlags);
        }

        if ($this->value !== null) {
            $dictionary->add('V', new StringType($this->value));
        }

        if ($this->defaultValue !== null) {
            $dictionary->add('DV', new StringType($this->defaultValue));
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
        return $this->appearance === null ? [] : [$this->appearance];
    }
}
