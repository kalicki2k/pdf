<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Page\LinkTarget;

final readonly class TextSegment
{
    public function __construct(
        public string $text,
        public ?LinkTarget $link = null,
    ) {
    }
}
