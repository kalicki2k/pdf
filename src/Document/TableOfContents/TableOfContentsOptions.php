<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TableOfContents;

use InvalidArgumentException;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;

final readonly class TableOfContentsOptions
{
    public string $title;
    public string $fontName;
    public ?EmbeddedFontSource $embeddedFont;
    public float $titleSize;
    public float $entrySize;
    public Margin $margin;
    public ?PageSize $pageSize;
    public TableOfContentsPlacement $placement;
    public TableOfContentsStyle $style;

    public function __construct(
        string $title = 'Contents',
        string $fontName = 'Helvetica',
        ?EmbeddedFontSource $embeddedFont = null,
        float $titleSize = 18.0,
        float $entrySize = 12.0,
        ?Margin $margin = null,
        ?PageSize $pageSize = null,
        ?TableOfContentsPlacement $placement = null,
        ?TableOfContentsStyle $style = null,
    ) {
        $this->title = $title;
        $this->fontName = $fontName;
        $this->embeddedFont = $embeddedFont;
        $this->titleSize = $titleSize;
        $this->entrySize = $entrySize;
        $this->margin = $margin ?? Margin::all(Units::mm(20));
        $this->pageSize = $pageSize;
        $this->placement = $placement ?? TableOfContentsPlacement::end();
        $this->style = $style ?? new TableOfContentsStyle();

        if ($this->title === '') {
            throw new InvalidArgumentException('Table of contents title must not be empty.');
        }

        if ($this->fontName === '') {
            throw new InvalidArgumentException('Table of contents font name must not be empty.');
        }

        if ($this->titleSize <= 0.0) {
            throw new InvalidArgumentException('Table of contents title size must be greater than zero.');
        }

        if ($this->entrySize <= 0.0) {
            throw new InvalidArgumentException('Table of contents entry size must be greater than zero.');
        }

        foreach ([
            'top' => $this->margin->top,
            'right' => $this->margin->right,
            'bottom' => $this->margin->bottom,
            'left' => $this->margin->left,
        ] as $side => $value) {
            if ($value < 0.0) {
                throw new InvalidArgumentException(sprintf(
                    'Table of contents margin %s must be zero or greater.',
                    $side,
                ));
            }
        }
    }
}
