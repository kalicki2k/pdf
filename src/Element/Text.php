<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

class Text extends Element
{
    private ?int $markedContentId;
    private string $content;
    private string $font;
    private float $size;
    private ?string $colorOperator;
    private ?string $graphicsState;
    private ?string $tag;

    public function __construct(
        ?int $markedContentId,
        string $content,
        float $x,
        float $y,
        string $font,
        float $size,
        ?string $colorOperator = null,
        ?string $graphicsState = null,
        ?string $tag = null,
    ) {
        $this->markedContentId = $markedContentId;
        $this->content = $content;
        $this->x = $x;
        $this->y = $y;
        $this->font = $font;
        $this->size = $size;
        $this->colorOperator = $colorOperator;
        $this->graphicsState = $graphicsState;
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

        return $output . 'ET' . PHP_EOL . 'Q';
    }
}
