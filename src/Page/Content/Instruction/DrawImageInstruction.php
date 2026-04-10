<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

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

    protected function writeInstruction(PdfOutput $output): void
    {
        $output->write('q' . PHP_EOL);

        if ($this->tag !== null && $this->markedContentId !== null) {
            $output->write("/$this->tag << /MCID $this->markedContentId >> BDC" . PHP_EOL);
        } elseif ($this->tag !== null) {
            $output->write("/$this->tag BMC" . PHP_EOL);
        }

        $output->write(self::formatNumber($this->width) . ' 0 0 '
            . self::formatNumber($this->height) . ' '
            . self::formatNumber($this->x) . ' '
            . self::formatNumber($this->y) . ' cm' . PHP_EOL
            . '/' . $this->resourceName . ' Do' . PHP_EOL);

        if ($this->tag !== null) {
            $output->write('EMC' . PHP_EOL);
        }

        $output->write('Q');
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
