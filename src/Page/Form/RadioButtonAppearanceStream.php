<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use Kalle\Pdf\Object\StreamIndirectObject;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\Render\PdfOutput;

final class RadioButtonAppearanceStream extends StreamIndirectObject
{
    private const KAPPA = 0.5522847498;

    public function __construct(
        int $id,
        private readonly float $size,
        private readonly bool $checked,
    ) {
        parent::__construct($id);
    }

    protected function streamDictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('XObject'),
            'Subtype' => new NameType('Form'),
            'FormType' => 1,
            'BBox' => new ArrayType([0, 0, $this->size, $this->size]),
            'Resources' => new DictionaryType([]),
            'Length' => $length,
        ]);
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $radius = $this->size / 2;
        $lines = [
            '1 g',
            '0 G',
            '1 w',
            $this->buildCirclePath($radius, $radius, $radius - 0.5),
            'B',
        ];

        if ($this->checked) {
            $lines[] = '0 g';
            $lines[] = $this->buildCirclePath($radius, $radius, $radius * 0.45);
            $lines[] = 'f';
        }

        $this->writeLines($output, $lines);
    }

    private function buildCirclePath(float $centerX, float $centerY, float $radius): string
    {
        $kappa = $radius * self::KAPPA;

        return implode(PHP_EOL, [
            sprintf('%s %s m', $this->format($centerX), $this->format($centerY + $radius)),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX + $kappa),
                $this->format($centerY + $radius),
                $this->format($centerX + $radius),
                $this->format($centerY + $kappa),
                $this->format($centerX + $radius),
                $this->format($centerY),
            ),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX + $radius),
                $this->format($centerY - $kappa),
                $this->format($centerX + $kappa),
                $this->format($centerY - $radius),
                $this->format($centerX),
                $this->format($centerY - $radius),
            ),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX - $kappa),
                $this->format($centerY - $radius),
                $this->format($centerX - $radius),
                $this->format($centerY - $kappa),
                $this->format($centerX - $radius),
                $this->format($centerY),
            ),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX - $radius),
                $this->format($centerY + $kappa),
                $this->format($centerX - $kappa),
                $this->format($centerY + $radius),
                $this->format($centerX),
                $this->format($centerY + $radius),
            ),
        ]);
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
