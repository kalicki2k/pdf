<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class AnnotationBorderStyle
{
    /**
     * @param list<float> $dashPattern
     */
    public function __construct(
        public float $width = 1.0,
        public AnnotationBorderStyleType $style = AnnotationBorderStyleType::SOLID,
        public array $dashPattern = [],
    ) {
        if ($this->width < 0.0) {
            throw new InvalidArgumentException('Annotation border width must be zero or greater.');
        }

        foreach ($this->dashPattern as $dashLength) {
            if ($dashLength < 0.0) {
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

    public function pdfDictionaryContents(): string
    {
        $entries = [
            '/W ' . $this->formatNumber($this->width),
            '/S /' . $this->style->value,
        ];

        if ($this->style === AnnotationBorderStyleType::DASHED && $this->dashPattern !== []) {
            $entries[] = '/D [' . implode(' ', array_map($this->formatNumber(...), $this->dashPattern)) . ']';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
