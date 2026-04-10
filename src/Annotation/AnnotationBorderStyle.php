<?php

declare(strict_types=1);

namespace Kalle\Pdf\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;

final class AnnotationBorderStyle
{
    /**
     * @param list<float> $dashPattern
     */
    public function __construct(
        public readonly float $width = 1.0,
        public readonly AnnotationBorderStyleType $style = AnnotationBorderStyleType::SOLID,
        public readonly array $dashPattern = [],
    ) {
        if ($this->width < 0) {
            throw new InvalidArgumentException('Annotation border width must be zero or greater.');
        }

        foreach ($this->dashPattern as $dashLength) {
            if ($dashLength < 0) {
                throw new InvalidArgumentException('Annotation dash pattern values must be zero or greater.');
            }
        }
    }

    public static function solid(float $width = 1.0): self
    {
        return new self($width, AnnotationBorderStyleType::SOLID);
    }

    /**
     * @param list<float> $dashPattern
     */
    public static function dashed(float $width = 1.0, array $dashPattern = [3.0, 2.0]): self
    {
        return new self($width, AnnotationBorderStyleType::DASHED, $dashPattern);
    }

    public function toPdfDictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'W' => $this->width,
            'S' => new NameType($this->style->value),
        ]);

        if ($this->style === AnnotationBorderStyleType::DASHED && $this->dashPattern !== []) {
            $dictionary->add('D', new ArrayType($this->dashPattern));
        }

        return $dictionary;
    }
}
