<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class BidiRun
{
    public function __construct(
        public string $text,
        public TextDirection $direction,
        public int $embeddingLevel = 0,
        public int $isolateSequence = 0,
    ) {
    }
}
