<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

final readonly class Outline
{
    private function __construct(
        public string $title,
        public int $pageNumber,
        public OutlineDestination $destination,
        public int $level = 1,
        public bool $open = true,
        public ?OutlineStyle $style = null,
        public ?float $x = null,
        public ?float $y = null,
    ) {
        if ($this->title === '') {
            throw new InvalidArgumentException('Outline title must not be empty.');
        }

        if ($this->pageNumber < 1) {
            throw new InvalidArgumentException('Outline page number must be greater than zero.');
        }

        if ($this->level < 1) {
            throw new InvalidArgumentException('Outline level must be greater than zero.');
        }

        if ($this->pageNumber !== $this->destination->pageNumber) {
            throw new InvalidArgumentException('Outline page number must match the outline destination page number.');
        }
    }

    public static function page(string $title, int $pageNumber, int $level = 1, bool $open = true): self
    {
        self::assertPageNumber($pageNumber);

        return new self(
            $title,
            $pageNumber,
            OutlineDestination::xyzPage($pageNumber),
            $level,
            $open,
        );
    }

    public static function position(string $title, int $pageNumber, float $x, float $y, int $level = 1, bool $open = true): self
    {
        self::assertPageNumber($pageNumber);

        return new self(
            $title,
            $pageNumber,
            OutlineDestination::xyz($pageNumber, $x, $y),
            $level,
            $open,
            null,
            $x,
            $y,
        );
    }

    public static function fit(string $title, int $pageNumber, int $level = 1, bool $open = true): self
    {
        self::assertPageNumber($pageNumber);

        return new self(
            $title,
            $pageNumber,
            OutlineDestination::fit($pageNumber),
            $level,
            $open,
        );
    }

    public static function fitHorizontal(string $title, int $pageNumber, float $top, int $level = 1, bool $open = true): self
    {
        self::assertPageNumber($pageNumber);

        return new self(
            $title,
            $pageNumber,
            OutlineDestination::fitHorizontal($pageNumber, $top),
            $level,
            $open,
            null,
            null,
            $top,
        );
    }

    public static function fitRectangle(
        string $title,
        int $pageNumber,
        float $left,
        float $bottom,
        float $right,
        float $top,
        int $level = 1,
        bool $open = true,
    ): self {
        self::assertPageNumber($pageNumber);

        return new self(
            $title,
            $pageNumber,
            OutlineDestination::fitRectangle($pageNumber, $left, $bottom, $right, $top),
            $level,
            $open,
        );
    }

    public static function named(string $title, string $name, int $pageNumber, int $level = 1, bool $open = true): self
    {
        self::assertPageNumber($pageNumber);

        return new self(
            $title,
            $pageNumber,
            OutlineDestination::named($name, $pageNumber),
            $level,
            $open,
        );
    }

    public function hasPosition(): bool
    {
        return $this->destination->hasExplicitPosition();
    }

    public function withLevel(int $level): self
    {
        return new self($this->title, $this->pageNumber, $this->destination, $level, $this->open, $this->style, $this->x, $this->y);
    }

    public function opened(): self
    {
        return new self($this->title, $this->pageNumber, $this->destination, $this->level, true, $this->style, $this->x, $this->y);
    }

    public function closed(): self
    {
        return new self($this->title, $this->pageNumber, $this->destination, $this->level, false, $this->style, $this->x, $this->y);
    }

    public function withDestination(OutlineDestination $destination): self
    {
        return new self(
            $this->title,
            $destination->pageNumber,
            $destination,
            $this->level,
            $this->open,
            $this->style,
            $destination->isXyz() ? $destination->x : null,
            $destination->isXyz() ? $destination->y : null,
        );
    }

    public function asGoToAction(): self
    {
        return $this->withDestination($this->destination->asGoToAction());
    }

    public function withStyle(OutlineStyle $style): self
    {
        return new self($this->title, $this->pageNumber, $this->destination, $this->level, $this->open, $style, $this->x, $this->y);
    }

    public function withColor(Color $color): self
    {
        return $this->withStyle(($this->style ?? new OutlineStyle())->withColor($color));
    }

    public function bold(): self
    {
        return $this->withStyle(($this->style ?? new OutlineStyle())->withBold());
    }

    public function italic(): self
    {
        return $this->withStyle(($this->style ?? new OutlineStyle())->withItalic());
    }

    private static function assertPageNumber(int $pageNumber): void
    {
        if ($pageNumber < 1) {
            throw new InvalidArgumentException('Outline page number must be greater than zero.');
        }
    }
}
