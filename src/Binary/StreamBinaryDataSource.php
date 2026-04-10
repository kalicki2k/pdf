<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final class StreamBinaryDataSource implements BinaryDataSource
{
    private const READ_CHUNK_BYTES = 8192;

    /** @var resource */
    private $stream;

    /** @var list<resource> */
    private array $streamsToClose = [];

    private bool $isSeekable;

    private ?int $length;

    private bool $isConsumed = false;

    /**
     * @param resource $stream
     */
    public function __construct(
        $stream,
        ?int $length = null,
        bool $closeOnDestruct = false,
    ) {
        if (!is_resource($stream)) {
            throw new RuntimeException('Binary data stream must be a valid resource.');
        }

        if ($length !== null && $length < 0) {
            throw new RuntimeException('Binary data stream length must not be negative.');
        }

        $metadata = stream_get_meta_data($stream);
        $mode = (string) $metadata['mode'];

        if (!str_contains($mode, 'r') && !str_contains($mode, '+')) {
            throw new RuntimeException('Binary data stream must be readable.');
        }

        $this->stream = $stream;
        $this->isSeekable = $metadata['seekable'] === true;
        $this->length = $length;

        if ($closeOnDestruct) {
            $this->streamsToClose[] = $stream;
        }
    }

    public function length(): int
    {
        if ($this->length !== null) {
            return $this->length;
        }

        if (!$this->isSeekable) {
            throw new RuntimeException(
                'Unable to determine the length of a non-seekable binary stream without buffering it. '
                . 'Provide the byte length explicitly.',
            );
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

    public function slice(int $offset, int $length): string
    {
        if ($offset < 0 || $length < 0) {
            throw new RuntimeException('Binary data slice offset and length must not be negative.');
        }

        if ($length === 0) {
            return '';
        }

        if (!$this->isSeekable) {
            throw new RuntimeException('Unable to slice a non-seekable binary stream without buffering it.');
        }

        return $this->withStreamAtOffset($offset, function () use ($length): string {
            $bytes = '';
            $remaining = $length;

            while ($remaining > 0 && !feof($this->stream)) {
                $chunk = fread($this->stream, $remaining);

                if ($chunk === false) {
                    throw new RuntimeException('Unable to read binary data stream.');
                }

                if ($chunk === '') {
                    break;
                }

                $bytes .= $chunk;
                $remaining -= strlen($chunk);
            }

            return $bytes;
        });
    }

    public function writeTo(PdfOutput $output): void
    {
        if (!$this->isSeekable) {
            $this->writeNonSeekableStream($output);

            return;
        }

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
        foreach ($this->streamsToClose as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->streamsToClose = [];
    }

    private function writeNonSeekableStream(PdfOutput $output): void
    {
        if ($this->isConsumed) {
            throw new RuntimeException('Unable to replay a non-seekable binary stream after it has been consumed.');
        }

        $length = 0;

        while (!feof($this->stream)) {
            $chunk = fread($this->stream, self::READ_CHUNK_BYTES);

            if ($chunk === false) {
                throw new RuntimeException('Unable to read binary data stream.');
            }

            if ($chunk === '') {
                continue;
            }

            $length += strlen($chunk);
            $output->write($chunk);
        }

        if ($this->length !== null && $this->length !== $length) {
            throw new RuntimeException('Binary data stream length does not match the provided byte length.');
        }

        $this->length ??= $length;
        $this->isConsumed = true;
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

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withStreamAtOffset(int $offset, callable $callback)
    {
        $position = $this->currentPosition();

        if (fseek($this->stream, $offset) !== 0) {
            throw new RuntimeException('Unable to seek binary data stream.');
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
