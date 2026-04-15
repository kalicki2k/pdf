<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextSegment;

final readonly class TableCell
{
    public TableCellContent $content;
    public string $text;
    public int $colspan;
    public int $rowspan;

    public function __construct(
        string | TableCellContent $content,
        int $colspan = 1,
        int $rowspan = 1,
        public ?Color $backgroundColor = null,
        public VerticalAlign $verticalAlign = VerticalAlign::TOP,
        public ?TableHeaderScope $headerScope = null,
        public ?TextAlign $horizontalAlign = null,
        public bool $noWrap = false,
        public ?CellPadding $padding = null,
        public ?Border $border = null,
    ) {
        if ($colspan < 1) {
            throw new InvalidArgumentException('Table cell colspan must be at least 1.');
        }

        if ($rowspan < 1) {
            throw new InvalidArgumentException('Table cell rowspan must be at least 1.');
        }

        $this->content = is_string($content)
            ? TableCellContent::text($content)
            : $content;
        $this->text = $this->content->plainText;
        $this->colspan = $colspan;
        $this->rowspan = $rowspan;
    }

    public static function text(string $text, int $colspan = 1, int $rowspan = 1): self
    {
        return new self($text, $colspan, $rowspan);
    }

    public static function segments(TextSegment ...$segments): self
    {
        return new self(TableCellContent::segments(...$segments));
    }

    public function withColspan(int $colspan): self
    {
        return $this->copy(
            $colspan,
        );
    }

    public function withRowspan(int $rowspan): self
    {
        return $this->copy(
            rowspan: $rowspan,
        );
    }

    public function withBackgroundColor(Color $backgroundColor): self
    {
        return $this->copy(
            backgroundColor: $backgroundColor,
        );
    }

    public function withVerticalAlign(VerticalAlign $verticalAlign): self
    {
        return $this->copy(
            verticalAlign: $verticalAlign,
        );
    }

    public function withHeaderScope(TableHeaderScope $headerScope): self
    {
        return $this->copy(
            headerScope: $headerScope,
        );
    }

    public function withHorizontalAlign(TextAlign $horizontalAlign): self
    {
        return $this->copy(
            horizontalAlign: $horizontalAlign,
        );
    }

    public function withPadding(CellPadding $padding): self
    {
        return $this->copy(
            padding: $padding,
        );
    }

    public function withNoWrap(bool $noWrap = true): self
    {
        return $this->copy(
            noWrap: $noWrap,
        );
    }

    public function withBorder(Border $border): self
    {
        return $this->copy(
            border: $border,
        );
    }

    private function copy(
        ?int $colspan = null,
        ?int $rowspan = null,
        ?Color $backgroundColor = null,
        ?VerticalAlign $verticalAlign = null,
        ?TableHeaderScope $headerScope = null,
        ?TextAlign $horizontalAlign = null,
        ?bool $noWrap = null,
        ?CellPadding $padding = null,
        ?Border $border = null,
    ): self {
        return new self(
            $this->content,
            $colspan ?? $this->colspan,
            $rowspan ?? $this->rowspan,
            $backgroundColor ?? $this->backgroundColor,
            $verticalAlign ?? $this->verticalAlign,
            $headerScope ?? $this->headerScope,
            $horizontalAlign ?? $this->horizontalAlign,
            $noWrap ?? $this->noWrap,
            $padding ?? $this->padding,
            $border ?? $this->border,
        );
    }
}
