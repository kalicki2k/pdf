<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content\Instruction;

final class LineInstruction extends ContentInstruction
{
    public function __construct(
        private readonly float $startX,
        private readonly float $startY,
        private readonly float $endX,
        private readonly float $endY,
        private readonly float $width = 1.0,
        private readonly ?string $strokeColorOperator = null,
        private readonly ?string $graphicsState = null,
    ) {
    }

    public function render(): string
    {
        $output = 'q' . PHP_EOL;

        if ($this->strokeColorOperator !== null) {
            $output .= $this->strokeColorOperator . PHP_EOL;
        }

        if ($this->graphicsState !== null) {
            $output .= '/' . $this->graphicsState . ' gs' . PHP_EOL;
        }

        $output .= self::formatNumber($this->width) . ' w' . PHP_EOL;
        $output .= self::formatNumber($this->startX) . ' ' . self::formatNumber($this->startY) . ' m' . PHP_EOL;
        $output .= self::formatNumber($this->endX) . ' ' . self::formatNumber($this->endY) . ' l' . PHP_EOL;
        $output .= 'S' . PHP_EOL;

        return $output . 'Q';
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
