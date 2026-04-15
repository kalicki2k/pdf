<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function array_values;
use function implode;

use Kalle\Pdf\Text\TextSegment;

final readonly class TableCellContent
{
    /**
     * @param list<TextSegment> $segments
     */
    public function __construct(
        public string $plainText,
        public array $segments = [],
    ) {
    }

    public static function text(string $text): self
    {
        return new self($text);
    }

    public static function segments(TextSegment ...$segments): self
    {
        return new self(
            implode('', array_map(
                static fn (TextSegment $segment): string => $segment->text,
                $segments,
            )),
            array_values($segments),
        );
    }

    public function isRichText(): bool
    {
        return $this->segments !== [];
    }
}
