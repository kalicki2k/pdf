<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use InvalidArgumentException;
use RuntimeException;

final class StreamPdfOutput implements PdfOutput
{
    /** @var resource */
    private $stream;

    private int $offset;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('PDF output stream must be a valid stream resource.');
        }

        $this->stream = $stream;

        $currentOffset = ftell($this->stream);
        $this->offset = $currentOffset === false ? 0 : $currentOffset;
    }

    public function write(string $bytes): void
    {
        $remainingBytes = $bytes;

        while ($remainingBytes !== '') {
            $writtenBytes = fwrite($this->stream, $remainingBytes);

            if ($writtenBytes === false || $writtenBytes === 0) {
                throw new RuntimeException('Unable to write PDF bytes to output stream.');
            }

            $this->offset += $writtenBytes;
            $remainingBytes = substr($remainingBytes, $writtenBytes);
        }
    }

    public function offset(): int
    {
        return $this->offset;
    }
}
