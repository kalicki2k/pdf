<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Layout\Table\VerticalAlign;

final readonly class TableCell
{
    public function __construct(
        public string $text,
        public int $colspan = 1,
        public int $rowspan = 1,
        public ?Color $backgroundColor = null,
        public VerticalAlign $verticalAlign = VerticalAlign::TOP,
        public ?TableHeaderScope $headerScope = null,
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
        return new self($this->text, $colspan, $this->rowspan, $this->backgroundColor, $this->verticalAlign, $this->headerScope);
    }

    public function withRowspan(int $rowspan): self
    {
        return new self($this->text, $this->colspan, $rowspan, $this->backgroundColor, $this->verticalAlign, $this->headerScope);
    }

    public function withBackgroundColor(Color $backgroundColor): self
    {
        return new self($this->text, $this->colspan, $this->rowspan, $backgroundColor, $this->verticalAlign, $this->headerScope);
    }

    public function withVerticalAlign(VerticalAlign $verticalAlign): self
    {
        return new self($this->text, $this->colspan, $this->rowspan, $this->backgroundColor, $verticalAlign, $this->headerScope);
    }

    public function withHeaderScope(TableHeaderScope $headerScope): self
    {
        return new self($this->text, $this->colspan, $this->rowspan, $this->backgroundColor, $this->verticalAlign, $headerScope);
    }
}
