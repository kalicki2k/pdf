<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

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

    protected function writeInstruction(PdfOutput $output): void
    {
        $output->write('q' . PHP_EOL);

        if ($this->strokeColorOperator !== null) {
            $output->write($this->strokeColorOperator . PHP_EOL);
        }

        if ($this->graphicsState !== null) {
            $output->write('/' . $this->graphicsState . ' gs' . PHP_EOL);
        }

        $output->write(self::formatNumber($this->width) . ' w' . PHP_EOL);
        $output->write(self::formatNumber($this->startX) . ' ' . self::formatNumber($this->startY) . ' m' . PHP_EOL);
        $output->write(self::formatNumber($this->endX) . ' ' . self::formatNumber($this->endY) . ' l' . PHP_EOL);
        $output->write('S' . PHP_EOL);
        $output->write('Q');
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
