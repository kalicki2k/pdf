<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

class Text extends Element
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
    }

    public function render(): string
    {
        $output = 'q' . PHP_EOL
            . 'BT' . PHP_EOL
            . "/$this->font $this->size Tf" . PHP_EOL
            . "$this->x $this->y Td" . PHP_EOL;

        if ($this->colorOperator !== null) {
            $output .= $this->colorOperator . PHP_EOL;
        }

        if ($this->graphicsState !== null) {
            $output .= "/$this->graphicsState gs" . PHP_EOL;
        }

        if ($this->tag !== null && $this->markedContentId !== null) {
            $output .= "/$this->tag << /MCID $this->markedContentId >> BDC" . PHP_EOL;
        }

        $output .= $this->content . ' Tj' . PHP_EOL;

        if ($this->tag !== null && $this->markedContentId !== null) {
            $output .= 'EMC' . PHP_EOL;
        }

        $output .= 'ET' . PHP_EOL;

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

        if ($this->underline) {
            $decorations[] = $this->renderFilledLine($this->x, $this->y - ($this->size * 0.18), $this->width, $lineHeight);
        }

        if ($this->strikethrough) {
            $decorations[] = $this->renderFilledLine($this->x, $this->y + ($this->size * 0.3), $this->width, $lineHeight);
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
