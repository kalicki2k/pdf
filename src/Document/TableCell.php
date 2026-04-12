<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Text\TextAlign;

final readonly class TableCell
{
    public function __construct(
        public string $text,
        public int $colspan = 1,
        public int $rowspan = 1,
        public ?Color $backgroundColor = null,
        public VerticalAlign $verticalAlign = VerticalAlign::TOP,
        public ?TableHeaderScope $headerScope = null,
        public ?TextAlign $horizontalAlign = null,
        public ?CellPadding $padding = null,
        public ?Border $border = null,
    ) {
        if ($this->colspan < 1) {
            throw new InvalidArgumentException('Table cell colspan must be at least 1.');
        }

        if ($this->rowspan < 1) {
            throw new InvalidArgumentException('Table cell rowspan must be at least 1.');
        }
    }

    public static function text(string $text, int $colspan = 1, int $rowspan = 1): self
    {
        return new self($text, $colspan, $rowspan);
    }

    public function withColspan(int $colspan): self
    {
        return new self(
            $this->text,
            $colspan,
            $this->rowspan,
            $this->backgroundColor,
            $this->verticalAlign,
            $this->headerScope,
            $this->horizontalAlign,
            $this->padding,
            $this->border,
        );
    }

    public function withRowspan(int $rowspan): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $rowspan,
            $this->backgroundColor,
            $this->verticalAlign,
            $this->headerScope,
            $this->horizontalAlign,
            $this->padding,
            $this->border,
        );
    }

    public function withBackgroundColor(Color $backgroundColor): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $this->rowspan,
            $backgroundColor,
            $this->verticalAlign,
            $this->headerScope,
            $this->horizontalAlign,
            $this->padding,
            $this->border,
        );
    }

    public function withVerticalAlign(VerticalAlign $verticalAlign): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $this->rowspan,
            $this->backgroundColor,
            $verticalAlign,
            $this->headerScope,
            $this->horizontalAlign,
            $this->padding,
            $this->border,
        );
    }

    public function withHeaderScope(TableHeaderScope $headerScope): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $this->rowspan,
            $this->backgroundColor,
            $this->verticalAlign,
            $headerScope,
            $this->horizontalAlign,
            $this->padding,
            $this->border,
        );
    }

    public function withHorizontalAlign(TextAlign $horizontalAlign): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $this->rowspan,
            $this->backgroundColor,
            $this->verticalAlign,
            $this->headerScope,
            $horizontalAlign,
            $this->padding,
            $this->border,
        );
    }

    public function withPadding(CellPadding $padding): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $this->rowspan,
            $this->backgroundColor,
            $this->verticalAlign,
            $this->headerScope,
            $this->horizontalAlign,
            $padding,
            $this->border,
        );
    }

    public function withBorder(Border $border): self
    {
        return new self(
            $this->text,
            $this->colspan,
            $this->rowspan,
            $this->backgroundColor,
            $this->verticalAlign,
            $this->headerScope,
            $this->horizontalAlign,
            $this->padding,
            $border,
        );
    }
}
