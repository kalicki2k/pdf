<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Instruction;

use Kalle\Pdf\Render\PdfOutput;

class TextInstruction extends ContentInstruction
{
    private ?int $markedContentId;
    private string $content;
    private string $font;
    private float $size;
    private float $width;
    private ?string $colorOperator;
    private ?string $graphicsState;
    private bool $underline;
    private bool $strikethrough;
    private ?string $tag;
    private float $leadingDecorationInset;
    private float $trailingDecorationInset;

    public function __construct(
        ?int $markedContentId,
        string $content,
        float $x,
        float $y,
        string $font,
        float $size,
        float $width,
        ?string $colorOperator = null,
        ?string $graphicsState = null,
        bool $underline = false,
        bool $strikethrough = false,
        ?string $tag = null,
        float $leadingDecorationInset = 0.0,
        float $trailingDecorationInset = 0.0,
    ) {
        $this->markedContentId = $markedContentId;
        $this->content = $content;
        $this->x = $x;
        $this->y = $y;
        $this->font = $font;
        $this->size = $size;
        $this->width = $width;
        $this->colorOperator = $colorOperator;
        $this->graphicsState = $graphicsState;
        $this->underline = $underline;
        $this->strikethrough = $strikethrough;
        $this->tag = $tag;
        $this->leadingDecorationInset = $leadingDecorationInset;
        $this->trailingDecorationInset = $trailingDecorationInset;
    }

    protected function writeInstruction(PdfOutput $output): void
    {
        $output->write('q' . PHP_EOL);

        if ($this->tag !== null && $this->markedContentId !== null) {
            $output->write("/$this->tag << /MCID $this->markedContentId >> BDC" . PHP_EOL);
        } elseif ($this->tag !== null) {
            $output->write("/$this->tag BMC" . PHP_EOL);
        }

        $output->write('BT' . PHP_EOL
            . "/$this->font $this->size Tf" . PHP_EOL
            . "$this->x $this->y Td" . PHP_EOL);

        if ($this->colorOperator !== null) {
            $output->write($this->colorOperator . PHP_EOL);
        }

        if ($this->graphicsState !== null) {
            $output->write("/$this->graphicsState gs" . PHP_EOL);
        }

        $output->write($this->content . ' Tj' . PHP_EOL);
        $output->write('ET' . PHP_EOL);

        if ($this->tag !== null) {
            $output->write('EMC' . PHP_EOL);
        }

        $this->writeDecorations($output);
        $output->write('Q');
    }

    private function writeDecorations(PdfOutput $output): void
    {
        if ($this->width <= 0.0) {
            return;
        }

        $lineHeight = max(0.5, $this->size * 0.05);
        $decorationX = $this->x + $this->leadingDecorationInset;
        $decorationWidth = max(0.0, $this->width - $this->leadingDecorationInset - $this->trailingDecorationInset);

        if ($this->underline) {
            $output->write(
                $this->renderFilledLine($decorationX, $this->y - ($this->size * 0.18), $decorationWidth, $lineHeight) . PHP_EOL,
            );
        }

        if ($this->strikethrough) {
            $output->write(
                $this->renderFilledLine($decorationX, $this->y + ($this->size * 0.3), $decorationWidth, $lineHeight) . PHP_EOL,
            );
        }
    }

    private function renderFilledLine(float $x, float $y, float $width, float $height): string
    {
        return self::formatNumber($x) . ' '
            . self::formatNumber($y) . ' '
            . self::formatNumber($width) . ' '
            . self::formatNumber($height)
            . ' re f';
    }

    private static function formatNumber(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
