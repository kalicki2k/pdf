<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

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

    public static function plain(string $text): self
    {
        return new self($text);
    }

    public static function colored(string $text, Color $color): self
    {
        return new self(text: $text, color: $color);
    }

    public static function link(
        string $text,
        LinkTarget $target,
        ?Color $color = null,
        bool $underline = true,
    ): self {
        return new self(
            text: $text,
            color: $color,
            link: $target,
            underline: $underline,
        );
    }

    public static function bold(string $text, ?Color $color = null): self
    {
        return new self(text: $text, color: $color, bold: true);
    }

    public static function italic(string $text, ?Color $color = null): self
    {
        return new self(text: $text, color: $color, italic: true);
    }

    public static function underlined(string $text, ?Color $color = null): self
    {
        return new self(text: $text, color: $color, underline: true);
    }

    public static function strikethrough(string $text, ?Color $color = null): self
    {
        return new self(text: $text, color: $color, strikethrough: true);
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
