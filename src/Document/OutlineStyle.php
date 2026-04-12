<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;

final readonly class OutlineStyle
{
    public function __construct(
        public ?Color $color = null,
        public bool $bold = false,
        public bool $italic = false,
        public int $additionalFlags = 0,
    ) {
    }

    public function withColor(Color $color): self
    {
        return new self($color, $this->bold, $this->italic, $this->additionalFlags);
    }

    public function withBold(bool $bold = true): self
    {
        return new self($this->color, $bold, $this->italic, $this->additionalFlags);
    }

    public function withItalic(bool $italic = true): self
    {
        return new self($this->color, $this->bold, $italic, $this->additionalFlags);
    }

    public function withAdditionalFlags(int $flags): self
    {
        return new self($this->color, $this->bold, $this->italic, $flags);
    }

    /**
     * @return list<float>|null
     */
    public function pdfRgbComponents(): ?array
    {
        if ($this->color === null) {
            return null;
        }

        return match ($this->color->space) {
            ColorSpace::RGB => $this->color->components(),
            ColorSpace::GRAY => [
                $this->color->components()[0],
                $this->color->components()[0],
                $this->color->components()[0],
            ],
            ColorSpace::CMYK => null,
        };
    }

    public function pdfFlags(): int
    {
        $flags = 0;

        if ($this->italic) {
            $flags |= 1;
        }

        if ($this->bold) {
            $flags |= 2;
        }

        return $flags | $this->additionalFlags;
    }
}
