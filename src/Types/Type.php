<?php

declare(strict_types=1);

namespace Kalle\Pdf\Types;

interface Type
{
    public function render(): string;
}
