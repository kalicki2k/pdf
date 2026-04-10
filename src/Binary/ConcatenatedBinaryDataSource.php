<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final readonly class ConcatenatedBinaryDataSource implements RandomAccessBinaryDataSource
{
    /**
     * @param list<BinaryData> $segments
     */
    public function __construct(
        private array $segments,
    ) {
    }

    public function length(): int
    {
        $length = 0;

        foreach ($this->segments as $segment) {
            $length += $segment->length();
        }

        return $length;
    }

    public function slice(int $offset, int $length): string
    {
        if ($offset < 0 || $length < 0) {
            throw new RuntimeException('Binary data slice offset and length must not be negative.');
        }

        if ($length === 0) {
            return '';
        }

        $bytes = '';
        $remaining = $length;
        $cursor = 0;

        foreach ($this->segments as $segment) {
            $segmentLength = $segment->length();

            if ($offset >= $cursor + $segmentLength) {
                $cursor += $segmentLength;

                continue;
            }

            $segmentOffset = max(0, $offset - $cursor);
            $chunk = $segment->slice($segmentOffset, $remaining);

            if ($chunk !== '') {
                $bytes .= $chunk;
                $remaining -= strlen($chunk);
            }

            if ($remaining === 0) {
                break;
            }

            $cursor += $segmentLength;
        }

        return $bytes;
    }

    public function writeTo(PdfOutput $output): void
    {
        foreach ($this->segments as $segment) {
            $segment->writeTo($output);
        }
    }

    public function close(): void
    {
    }
}
