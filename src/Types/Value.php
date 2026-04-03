<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

interface Value
{
    public function render(): string;
}
