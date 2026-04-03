<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

use Kalle\Pdf\Utilities\PdfStringEscaper;

final class StringValue implements Value
{
    public function __construct(private readonly string $value)
    {
    }

    public function render(): string
    {
        return '(' . PdfStringEscaper::escape($this->value) . ')';
    }
}
