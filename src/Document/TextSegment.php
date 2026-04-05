<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final readonly class TextSegment
{
    public function __construct(
        public string $text,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public ?LinkTarget $link = null,
        public bool $bold = false,
        public bool $italic = false,
        public bool $underline = false,
        public bool $strikethrough = false,
    ) {
    }

    public function withDefaults(?Color $color, ?Opacity $opacity): self
    {
        return new self(
            $this->text,
            $this->color ?? $color,
            $this->opacity ?? $opacity,
            $this->link,
            $this->bold,
            $this->italic,
            $this->underline,
            $this->strikethrough,
        );
    }
}
