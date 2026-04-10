<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content\Instruction;

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

    public function render(): string
    {
        $output = 'q' . PHP_EOL;

        if ($this->tag !== null && $this->markedContentId !== null) {
            $output .= "/$this->tag << /MCID $this->markedContentId >> BDC" . PHP_EOL;
        } elseif ($this->tag !== null) {
            $output .= "/$this->tag BMC" . PHP_EOL;
        }

        $output .= 'BT' . PHP_EOL
            . "/$this->font $this->size Tf" . PHP_EOL
            . "$this->x $this->y Td" . PHP_EOL;

        if ($this->colorOperator !== null) {
            $output .= $this->colorOperator . PHP_EOL;
        }

        if ($this->graphicsState !== null) {
            $output .= "/$this->graphicsState gs" . PHP_EOL;
        }

        $output .= $this->content . ' Tj' . PHP_EOL;
        $output .= 'ET' . PHP_EOL;

        if ($this->tag !== null) {
            $output .= 'EMC' . PHP_EOL;
        }

        foreach ($this->renderDecorations() as $decoration) {
            $output .= $decoration . PHP_EOL;
        }

        return $output . 'Q';
    }

    /**
     * @return list<string>
     */
    private function renderDecorations(): array
    {
        if ($this->width <= 0.0) {
            return [];
        }

        $decorations = [];
        $lineHeight = max(0.5, $this->size * 0.05);
        $decorationX = $this->x + $this->leadingDecorationInset;
        $decorationWidth = max(0.0, $this->width - $this->leadingDecorationInset - $this->trailingDecorationInset);

        if ($this->underline) {
            $decorations[] = $this->renderFilledLine($decorationX, $this->y - ($this->size * 0.18), $decorationWidth, $lineHeight);
        }

        if ($this->strikethrough) {
            $decorations[] = $this->renderFilledLine($decorationX, $this->y + ($this->size * 0.3), $decorationWidth, $lineHeight);
        }

        return $decorations;
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
