<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use RuntimeException;

final class BinaryData
{
    /** @var resource|null */
    private $stream = null;

    private int $length = 0;

    private function __construct()
    {
    }

    public static function fromString(string $bytes): self
    {
        $data = new self();
        $data->appendBytes($bytes);

        return $data;
    }

    public static function fromFile(string $path): self
    {
        $source = @fopen($path, 'rb');

        if ($source === false) {
            throw new RuntimeException("Unable to open binary data file '$path'.");
        }

        $data = new self();

        try {
            $copiedBytes = stream_copy_to_stream($source, $data->stream());

            if ($copiedBytes === false) {
                throw new RuntimeException("Unable to copy binary data file '$path'.");
            }

            $data->length = $copiedBytes;
        } finally {
            fclose($source);
        }

        return $data;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function contents(): string
    {
        if ($this->length === 0) {
            return '';
        }

        $stream = $this->stream();

        if (rewind($stream) === false) {
            throw new RuntimeException('Unable to rewind binary data buffer.');
        }

        $contents = stream_get_contents($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to read binary data buffer.');
        }

        if (fseek($stream, 0, SEEK_END) !== 0) {
            throw new RuntimeException('Unable to seek binary data buffer.');
        }

        return $contents;
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    private function appendBytes(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $stream = $this->stream();
        $remainingBytes = $bytes;

        while ($remainingBytes !== '') {
            $writtenBytes = fwrite($stream, $remainingBytes);

            if ($writtenBytes === false || $writtenBytes === 0) {
                throw new RuntimeException('Unable to append binary data bytes.');
            }

            $this->length += $writtenBytes;
            $remainingBytes = substr($remainingBytes, $writtenBytes);
        }
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if ($this->stream === null) {
            $stream = fopen('php://temp', 'w+b');

            if ($stream === false) {
                throw new RuntimeException('Unable to open binary data buffer.');
            }

            $this->stream = $stream;
        }

        return $this->stream;
    }
}
