<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

final class DrawImage extends Element
{
    public function __construct(
        private readonly string $resourceName,
        float $x,
        float $y,
        private readonly float $width,
        private readonly float $height,
    ) {
        $this->x = $x;
        $this->y = $y;
    }

    public function render(): string
    {
        return 'q' . PHP_EOL
            . self::formatNumber($this->width) . ' 0 0 '
            . self::formatNumber($this->height) . ' '
            . self::formatNumber($this->x) . ' '
            . self::formatNumber($this->y) . ' cm' . PHP_EOL
            . '/' . $this->resourceName . ' Do' . PHP_EOL
            . 'Q';
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
