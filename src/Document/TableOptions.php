<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextOptions;

final readonly class TableOptions
{
    public function __construct(
        public Border $border = new Border(0.5, 0.5, 0.5, 0.5),
        public TextOptions $textOptions = new TextOptions(fontSize: 12.0, lineHeight: 14.4),
        public ?TableCaption $caption = null,
        public ?TablePlacement $placement = null,
        public CellPadding $cellPadding = new CellPadding(4.0, 4.0, 4.0, 4.0),
        public bool $repeatHeaderOnPageBreak = false,
        public bool $repeatFooterOnPageBreak = false,
    ) {
    }

    public function withCaption(?TableCaption $caption): self
    {
        return new self(
            border: $this->border,
            textOptions: $this->textOptions,
            caption: $caption,
            placement: $this->placement,
            cellPadding: $this->cellPadding,
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
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
            repeatFooterOnPageBreak: $repeatFooterOnPageBreak,
        );
    }
}
