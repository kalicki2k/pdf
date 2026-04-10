<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final readonly class SlicedBinaryDataSource implements RandomAccessBinaryDataSource
{
    private const READ_CHUNK_BYTES = 8192;

    public function __construct(
        private BinaryData $data,
        private int $offset,
        private int $length,
    ) {
        if ($offset < 0 || $length < 0) {
            throw new RuntimeException('Binary data slice offset and length must not be negative.');
        }
    }

    public function length(): int
    {
        $sourceLength = $this->data->length();

        if ($this->offset >= $sourceLength) {
            return 0;
        }

        return min($this->length, $sourceLength - $this->offset);
    }

    public function slice(int $offset, int $length): string
    {
        if ($offset < 0 || $length < 0) {
            throw new RuntimeException('Binary data slice offset and length must not be negative.');
        }

        if ($length === 0) {
            return '';
        }

        $availableLength = $this->length();

        if ($offset >= $availableLength) {
            return '';
        }

        $length = min($length, $availableLength - $offset);

        return $this->data->slice($this->offset + $offset, $length);
    }

    public function writeTo(PdfOutput $output): void
    {
        $remaining = $this->length();
        $offset = 0;

        while ($remaining > 0) {
            $chunkLength = min(self::READ_CHUNK_BYTES, $remaining);
            $chunk = $this->slice($offset, $chunkLength);

            if ($chunk === '') {
                break;
            }

            $output->write($chunk);
            $offset += strlen($chunk);
            $remaining -= strlen($chunk);
        }
    }

    public function close(): void
    {
    }
}
