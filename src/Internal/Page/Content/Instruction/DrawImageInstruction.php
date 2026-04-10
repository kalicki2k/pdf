<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content\Instruction;

final class DrawImageInstruction extends ContentInstruction
{
    public function __construct(
        private readonly string $resourceName,
        float $x,
        float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly ?int $markedContentId = null,
        private readonly ?string $tag = null,
    ) {
        $this->x = $x;
        $this->y = $y;
    }

    public function render(): string
    {
        $output = 'q' . PHP_EOL;

        if ($this->tag !== null && $this->markedContentId !== null) {
            $output .= "/$this->tag << /MCID $this->markedContentId >> BDC" . PHP_EOL;
        } elseif ($this->tag !== null) {
            $output .= "/$this->tag BMC" . PHP_EOL;
        }

        $output .= self::formatNumber($this->width) . ' 0 0 '
            . self::formatNumber($this->height) . ' '
            . self::formatNumber($this->x) . ' '
            . self::formatNumber($this->y) . ' cm' . PHP_EOL
            . '/' . $this->resourceName . ' Do' . PHP_EOL;

        if ($this->tag !== null) {
            $output .= 'EMC' . PHP_EOL;
        }

        return $output . 'Q';
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
