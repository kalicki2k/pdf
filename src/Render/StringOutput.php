<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

/**
 * Buffers serialized PDF bytes in memory.
 */
final class StringOutput implements Output
{
    /** @var list<string> */
    private array $chunks = [];
    private int $offset = 0;

    public function write(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $this->chunks[] = $bytes;
        $this->offset += strlen($bytes);
    }

    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Returns all written bytes as a single string.
     */
    public function contents(): string
    {
        return implode('', $this->chunks);
    }
}
