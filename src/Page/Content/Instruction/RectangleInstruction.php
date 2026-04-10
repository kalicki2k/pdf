<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

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

    protected function writeInstruction(PdfOutput $output): void
    {
        $output->write('q' . PHP_EOL);

        if ($this->strokeColorOperator !== null) {
            $output->write($this->strokeColorOperator . PHP_EOL);
        }

        if ($this->fillColorOperator !== null) {
            $output->write($this->fillColorOperator . PHP_EOL);
        }

        if ($this->graphicsState !== null) {
            $output->write('/' . $this->graphicsState . ' gs' . PHP_EOL);
        }

        if ($this->strokeWidth !== null) {
            $output->write(self::formatNumber($this->strokeWidth) . ' w' . PHP_EOL);
        }

        $output->write(self::formatNumber($this->rectX) . ' '
            . self::formatNumber($this->rectY) . ' '
            . self::formatNumber($this->rectWidth) . ' '
            . self::formatNumber($this->rectHeight) . ' re' . PHP_EOL);
        $output->write($this->paintOperator() . PHP_EOL);
        $output->write('Q');
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
