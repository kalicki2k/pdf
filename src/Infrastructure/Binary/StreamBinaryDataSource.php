<?php

declare(strict_types=1);

namespace Kalle\Pdf\Infrastructure\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final class StreamBinaryDataSource implements BinaryDataSource
{
    private const READ_CHUNK_BYTES = 8192;

    /** @var resource */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct(
        $stream,
        private readonly ?int $length = null,
        private readonly bool $closeOnDestruct = false,
    ) {
        if (!is_resource($stream)) {
            throw new RuntimeException('Binary data stream must be a valid resource.');
        }

        if ($this->length !== null && $this->length < 0) {
            throw new RuntimeException('Binary data stream length must not be negative.');
        }

        $metadata = stream_get_meta_data($stream);

        if ($metadata['seekable'] !== true) {
            throw new RuntimeException('Binary data stream must be seekable.');
        }

        $mode = (string) $metadata['mode'];

        if (!str_contains($mode, 'r') && !str_contains($mode, '+')) {
            throw new RuntimeException('Binary data stream must be readable.');
        }

        $this->stream = $stream;
    }

    public function length(): int
    {
        if ($this->length !== null) {
            return $this->length;
        }

        return $this->withStreamFromStart(function (): int {
            if (fseek($this->stream, 0, SEEK_END) !== 0) {
                throw new RuntimeException('Unable to seek binary data stream.');
            }

            $length = ftell($this->stream);

            if ($length === false) {
                throw new RuntimeException('Unable to determine binary data stream length.');
            }

            return $length;
        });
    }

    public function contents(): string
    {
        return $this->withStreamFromStart(function (): string {
            $contents = stream_get_contents($this->stream);

            if ($contents === false) {
                throw new RuntimeException('Unable to read binary data stream.');
            }

            return $contents;
        });
    }

    public function writeTo(PdfOutput $output): void
    {
        $this->withStreamFromStart(function () use ($output): null {
            while (!feof($this->stream)) {
                $chunk = fread($this->stream, self::READ_CHUNK_BYTES);

                if ($chunk === false) {
                    throw new RuntimeException('Unable to read binary data stream.');
                }

                if ($chunk === '') {
                    continue;
                }

                $output->write($chunk);
            }

            return null;
        });
    }

    public function close(): void
    {
        if ($this->closeOnDestruct && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withStreamFromStart(callable $callback)
    {
        $position = $this->currentPosition();

        if (rewind($this->stream) === false) {
            throw new RuntimeException('Unable to rewind binary data stream.');
        }

        try {
            return $callback();
        } finally {
            if (fseek($this->stream, $position) !== 0) {
                throw new RuntimeException('Unable to restore binary data stream position.');
            }
        }
    }

    private function currentPosition(): int
    {
        $position = ftell($this->stream);

        if ($position === false) {
            throw new RuntimeException('Unable to determine binary data stream position.');
        }

        return $position;
    }
}
