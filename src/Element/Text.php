<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

class Text extends Element
{
    private int $markedContentId;
    private string $content;
    private string $font;
    private float $size;
    private string $tag;

    public function __construct(
        int $markedContentId,
        string $content,
        float $x,
        float $y,
        string $font,
        float $size,
        string $tag,
    ) {
        $this->markedContentId = $markedContentId;
        $this->content = $content;
        $this->x = $x;
        $this->y = $y;
        $this->font = $font;
        $this->size = $size;
        $this->tag = $tag;
    }

    public function render(): string
    {
        return 'BT' . PHP_EOL
            . "/$this->font $this->size Tf" . PHP_EOL
            . "$this->x $this->y Td" . PHP_EOL
            . "/$this->tag << /MCID $this->markedContentId >> BDC" . PHP_EOL
            . $this->content . ' Tj' . PHP_EOL
            . 'EMC' . PHP_EOL
            . 'ET';
    }
}
