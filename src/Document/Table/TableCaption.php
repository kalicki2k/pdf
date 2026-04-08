<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table;

use InvalidArgumentException;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Graphics\Color;

final readonly class TableCaption
{
    /**
     * @param string|list<TextSegment> $text
     */
    public function __construct(
        public string | array $text,
        public ?string $fontName = null,
        public ?int $size = null,
        public ?Color $color = null,
        public float $spacingAfter = 6.0,
    ) {
        if ($this->text === '' || $this->text === []) {
            throw new InvalidArgumentException('Table caption text must not be empty.');
        }

        if ($this->fontName !== null && $this->fontName === '') {
            throw new InvalidArgumentException('Table caption font name must not be empty.');
        }

        if ($this->size !== null && $this->size <= 0) {
            throw new InvalidArgumentException('Table caption font size must be greater than zero.');
        }

        if ($this->spacingAfter < 0) {
            throw new InvalidArgumentException('Table caption spacing must be zero or greater.');
        }
    }
}
