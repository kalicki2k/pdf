<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

interface BidiResolver
{
    /**
     * @return list<BidiRun>
     */
    public function resolve(string $text, TextDirection $baseDirection = TextDirection::LTR): array;
}
