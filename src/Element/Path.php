<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

final class Path extends Element
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

        foreach ($this->commands as $command) {
            $output .= $command . PHP_EOL;
        }

        $output .= $this->paintOperator . PHP_EOL;

        return $output . 'Q';
    }

    public static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
