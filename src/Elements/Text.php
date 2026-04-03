<?php

namespace Kalle\Pdf\Elements;

use Kalle\Pdf\Core\Element;
use Kalle\Pdf\Utilities\PdfStringEscaper;

class Text extends Element
{
    private int $markedContentId;
    private string $content;
    private string $font;
    private string $size;
    private string $tag;

    public function __construct(
        int $markedContentId,
        string $content,
        float $x,
        float $y,
        string $font,
        float $size,
        string $tag
    )
    {
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
        $content = PdfStringEscaper::escape(iconv('UTF-8', 'Windows-1252', $this->content));

        $output = "BT\n";
        $output .= "/{$this->font} {$this->size} Tf\n";
        $output .= "{$this->x} {$this->y} Td\n";
        $output .= "/{$this->tag} << /MCID {$this->markedContentId} >> BDC\n";
        $output .= "({$content}) Tj\n";
        $output .= "EMC\n";
        $output .= "ET";

        return $output;
    }
}

// BT
// /F1 12 Tf
// 50 700 Td
// /P << /MCID 0 >> BDC  % Begin Marked Content
// (Dies ist ein Absatz.) Tj
// EMC                      % End Marked Content
// ET