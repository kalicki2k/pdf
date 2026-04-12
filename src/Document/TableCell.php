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

final class TableCell
{
    public readonly TableCellContent $content;
    public readonly string $text;
    public readonly int $colspan;
    public readonly int $rowspan;
    public readonly ?Color $backgroundColor;
    public readonly VerticalAlign $verticalAlign;
    public readonly ?TableHeaderScope $headerScope;
    public readonly ?TextAlign $horizontalAlign;
    public readonly ?CellPadding $padding;
    public readonly ?Border $border;

    public function __construct(
        string | TableCellContent $content,
        int $colspan = 1,
        int $rowspan = 1,
        ?Color $backgroundColor = null,
        VerticalAlign $verticalAlign = VerticalAlign::TOP,
        ?TableHeaderScope $headerScope = null,
        ?TextAlign $horizontalAlign = null,
        ?CellPadding $padding = null,
        ?Border $border = null,
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
        $this->backgroundColor = $backgroundColor;
        $this->verticalAlign = $verticalAlign;
        $this->headerScope = $headerScope;
        $this->horizontalAlign = $horizontalAlign;
        $this->padding = $padding;
        $this->border = $border;
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
