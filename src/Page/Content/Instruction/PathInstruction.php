<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

final class PathInstruction extends ContentInstruction
{
    /**
     * @param list<string> $commands
     */
    public function __construct(
        private readonly array $commands,
        private readonly ?float $strokeWidth = null,
        private readonly ?string $strokeColorOperator = null,
        private readonly ?string $fillColorOperator = null,
        private readonly ?string $graphicsState = null,
        private readonly string $paintOperator = 'S',
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

        foreach ($this->commands as $command) {
            $output->write($command . PHP_EOL);
        }

        $output->write($this->paintOperator . PHP_EOL);
        $output->write('Q');
    }

    public static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
