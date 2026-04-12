<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Page\LinkTarget;

final readonly class TextSegment
{
    public function __construct(
        public string $text,
        public LinkTarget | TextLink | null $link = null,
    ) {
    }

    public static function plain(string $text): self
    {
        return new self($text);
    }

    public static function link(
        string $text,
        TextLink $link,
    ): self {
        return new self($text, $link);
    }
}
