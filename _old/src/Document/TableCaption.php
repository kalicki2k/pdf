<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Text\TextOptions;

final readonly class TableCaption
{
    public function __construct(
        public string $text,
        public ?TextOptions $textOptions = null,
        public float $spacingAfter = 4.0,
    ) {
        if ($this->text === '') {
            throw new InvalidArgumentException('Table caption text must not be empty.');
        }

        if ($this->spacingAfter < 0.0) {
            throw new InvalidArgumentException('Table caption spacing must not be negative.');
        }
    }

    public static function text(string $text): self
    {
        return new self($text);
    }

    public function withTextOptions(TextOptions $textOptions): self
    {
        return new self($this->text, $textOptions, $this->spacingAfter);
    }

    public function withSpacingAfter(float $spacingAfter): self
    {
        return new self($this->text, $this->textOptions, $spacingAfter);
    }
}
