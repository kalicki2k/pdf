<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final readonly class FileBinaryDataSource implements RandomAccessBinaryDataSource
{
    private const READ_CHUNK_BYTES = 8192;

    public function __construct(
        private string $path,
    ) {
        $stream = @fopen($this->path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open binary data file '$this->path'.");
        }

        fclose($stream);
    }

    public function length(): int
    {
        $stream = $this->openStream();

        try {
            $stat = fstat($stream);

            if ($stat === false) {
                throw new RuntimeException("Unable to determine binary data length for '$this->path'.");
            }

            return $stat['size'];
        } finally {
            fclose($stream);
        }
    }

    public function slice(int $offset, int $length): string
    {
        if ($offset < 0 || $length < 0) {
            throw new RuntimeException('Binary data slice offset and length must not be negative.');
        }

        if ($length === 0) {
            return '';
        }

        $stream = $this->openStream();

        try {
            if (fseek($stream, $offset) !== 0) {
                throw new RuntimeException("Unable to seek binary data file '$this->path'.");
            }

            return $this->readFromStream($stream, $length);
        } finally {
            fclose($stream);
        }
    }

    public function writeTo(PdfOutput $output): void
    {
        $stream = $this->openStream();

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::READ_CHUNK_BYTES);

                if ($chunk === false) {
                    throw new RuntimeException("Unable to read binary data file '$this->path'.");
                }

                if ($chunk === '') {
                    continue;
                }

                $output->write($chunk);
            }
        } finally {
            fclose($stream);
        }
    }

    public function close(): void
    {
    }

    /**
     * @return resource
     */
    private function openStream()
    {
        $stream = @fopen($this->path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open binary data file '$this->path'.");
        }

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function readFromStream($stream, int $length): string
    {
        $bytes = '';
        $remaining = $length;

        while ($remaining > 0 && !feof($stream)) {
            $chunk = fread($stream, $remaining);

            if ($chunk === false) {
                throw new RuntimeException("Unable to read binary data file '$this->path'.");
            }

            if ($chunk === '') {
                break;
            }

            $bytes .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $bytes;
    }
}
