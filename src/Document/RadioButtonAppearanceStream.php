<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;

final class RadioButtonAppearanceStream extends IndirectObject
{
    private const KAPPA = 0.5522847498;

    public function __construct(
        int $id,
        private readonly float $size,
        private readonly bool $checked,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $radius = $this->size / 2;
        $content = implode(PHP_EOL, array_filter([
            '1 g',
            '0 G',
            '1 w',
            $this->buildCirclePath($radius, $radius, $radius - 0.5),
            'B',
            $this->checked ? '0 g' : null,
            $this->checked ? $this->buildCirclePath($radius, $radius, $radius * 0.45) : null,
            $this->checked ? 'f' : null,
        ]));

        $dictionary = new DictionaryType([
            'Type' => new NameType('XObject'),
            'Subtype' => new NameType('Form'),
            'FormType' => 1,
            'BBox' => new ArrayType([0, 0, $this->size, $this->size]),
            'Resources' => new DictionaryType([]),
            'Length' => strlen($content),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $content . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
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
