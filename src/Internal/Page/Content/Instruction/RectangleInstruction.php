<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content\Instruction;

final class RectangleInstruction extends ContentInstruction
{
    public function __construct(
        private readonly float $rectX,
        private readonly float $rectY,
        private readonly float $rectWidth,
        private readonly float $rectHeight,
        private readonly ?float $strokeWidth = null,
        private readonly ?string $strokeColorOperator = null,
        private readonly ?string $fillColorOperator = null,
        private readonly ?string $graphicsState = null,
    ) {
    }

    public function render(): string
    {
        $output = 'q' . PHP_EOL;

        if ($this->strokeColorOperator !== null) {
            $output .= $this->strokeColorOperator . PHP_EOL;
        }

        if ($this->fillColorOperator !== null) {
            $output .= $this->fillColorOperator . PHP_EOL;
        }

        if ($this->graphicsState !== null) {
            $output .= '/' . $this->graphicsState . ' gs' . PHP_EOL;
        }

        if ($this->strokeWidth !== null) {
            $output .= self::formatNumber($this->strokeWidth) . ' w' . PHP_EOL;
        }

        $output .= self::formatNumber($this->rectX) . ' '
            . self::formatNumber($this->rectY) . ' '
            . self::formatNumber($this->rectWidth) . ' '
            . self::formatNumber($this->rectHeight) . ' re' . PHP_EOL;
        $output .= $this->paintOperator() . PHP_EOL;

        return $output . 'Q';
    }

    private function paintOperator(): string
    {
        if ($this->strokeWidth !== null && $this->fillColorOperator !== null) {
            return 'B';
        }

        if ($this->fillColorOperator !== null) {
            return 'f';
        }

        return 'S';
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
