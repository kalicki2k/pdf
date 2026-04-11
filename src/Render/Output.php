<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

/**
 * Sink for serialized PDF bytes.
 */
interface Output
{
    /**
     * Writes raw PDF bytes to the output target.
     */
    public function write(string $bytes): void;

    /**
     * Returns the number of bytes written so far.
     */
    public function offset(): int;
}
