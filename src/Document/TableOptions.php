<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextOptions;

final readonly class TableOptions
{
    public Border $border;
    public TextOptions $textOptions;
    public ?TableCaption $caption;
    public ?TablePlacement $placement;
    public CellPadding $cellPadding;
    public float $spacingBefore;
    public float $spacingAfter;
    public bool $repeatHeaderOnPageBreak;
    public bool $repeatFooterOnPageBreak;

    public static function make(
        Border $border = new Border(0.5, 0.5, 0.5, 0.5),
        ?TextOptions $textOptions = null,
        ?TableCaption $caption = null,
        ?TablePlacement $placement = null,
        CellPadding $cellPadding = new CellPadding(4.0, 4.0, 4.0, 4.0),
        float $spacingBefore = 0.0,
        float $spacingAfter = 0.0,
        bool $repeatHeaderOnPageBreak = false,
        bool $repeatFooterOnPageBreak = false,
    ): self {
        return new self(
            border: $border,
            textOptions: $textOptions ?? TextOptions::make(fontSize: 12.0, lineHeight: 14.4),
            caption: $caption,
            placement: $placement,
            cellPadding: $cellPadding,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            repeatHeaderOnPageBreak: $repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $repeatFooterOnPageBreak,
        );
    }

    private function __construct(
        Border $border = new Border(0.5, 0.5, 0.5, 0.5),
        ?TextOptions $textOptions = null,
        ?TableCaption $caption = null,
        ?TablePlacement $placement = null,
        CellPadding $cellPadding = new CellPadding(4.0, 4.0, 4.0, 4.0),
        float $spacingBefore = 0.0,
        float $spacingAfter = 0.0,
        bool $repeatHeaderOnPageBreak = false,
        bool $repeatFooterOnPageBreak = false,
    ) {
        $this->border = $border;
        $this->textOptions = $textOptions ?? TextOptions::make(fontSize: 12.0, lineHeight: 14.4);
        $this->caption = $caption;
        $this->placement = $placement;
        $this->cellPadding = $cellPadding;
        $this->spacingBefore = $spacingBefore;
        $this->spacingAfter = $spacingAfter;
        $this->repeatHeaderOnPageBreak = $repeatHeaderOnPageBreak;
        $this->repeatFooterOnPageBreak = $repeatFooterOnPageBreak;
    }

    public function withCaption(?TableCaption $caption): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withPlacement(?TablePlacement $placement): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withCellPadding(CellPadding $cellPadding): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withBorder(Border $border): self
    {
        return new self(
            border: $border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withTextOptions(TextOptions $textOptions): self
    {
        return new self(
            border: $this->border,
            textOptions: $textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withSpacingBefore(float $spacingBefore): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withSpacingAfter(float $spacingAfter): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withRepeatedHeaderOnPageBreak(bool $repeatHeaderOnPageBreak = true): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $this->repeatFooterOnPageBreak,
        );
    }

    public function withRepeatedFooterOnPageBreak(bool $repeatFooterOnPageBreak = true): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $this->caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
            spacingBefore: $this->spacingBefore,
            spacingAfter: $this->spacingAfter,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $repeatFooterOnPageBreak,
        );
    }
}
