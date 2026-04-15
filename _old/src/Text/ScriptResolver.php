<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

interface ScriptResolver
{
    /**
     * @return list<ScriptRun>
     */
    public function resolve(string $text, TextDirection $baseDirection = TextDirection::LTR): array;
}
