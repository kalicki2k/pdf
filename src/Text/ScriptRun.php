<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ScriptRun
{
    public function __construct(
        public string $text,
        public TextDirection $direction,
        public TextScript $script,
    ) {
    }
}
