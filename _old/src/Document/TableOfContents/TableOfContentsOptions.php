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
    public Margin $margin;
    public TableOfContentsPlacement $placement;
    public TableOfContentsStyle $style;

    public function __construct(
        public string $title = 'Contents',
        public string $fontName = 'Helvetica',
        public ?EmbeddedFontSource $embeddedFont = null,
        public float $titleSize = 18.0,
        public float $entrySize = 12.0,
        ?Margin $margin = null,
        public ?PageSize $pageSize = null,
        ?TableOfContentsPlacement $placement = null,
        ?TableOfContentsStyle $style = null,
    ) {
        $this->margin = $margin ?? Margin::all(Units::mm(20));
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
