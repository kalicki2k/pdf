<?php

declare(strict_types=1);

namespace Kalle\Pdf\Contents;

final readonly class ContentsWriter
{
    public function write(Contents $contents): string
    {
        return '<< /Length ' . $contents->length() . " >>\n"
            . "stream\n"
            . $contents->stream . "\n"
            . 'endstream';
    }
}
