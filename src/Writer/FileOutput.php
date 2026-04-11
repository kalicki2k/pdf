<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

use InvalidArgumentException;
use RuntimeException;

/**
 * Writes serialized PDF bytes to a file on disk.
 */
final class FileOutput implements Output
{
    /** @var resource|null */
    private $stream;

    private StreamOutput $streamOutput;

    public function __construct(private readonly string $path)
    {
        if ($path === '') {
            throw new InvalidArgumentException('PDF output path must not be empty.');
        }

        $stream = @fopen($path, 'wb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open PDF output file '$path' for writing.");
        }

        $this->stream = $stream;
        $this->streamOutput = new StreamOutput($stream);
    }

    public function write(string $bytes): void
    {
        $this->streamOutput->write($bytes);
    }

    public function offset(): int
    {
        return $this->streamOutput->offset();
    }

    public function close(): void
    {
        if (!is_resource($this->stream)) {
            return;
        }

        if (!fflush($this->stream)) {
            throw new RuntimeException("Unable to flush PDF output file '$this->path'.");
        }

        if (!fclose($this->stream)) {
            throw new RuntimeException("Unable to close PDF output file '$this->path'.");
        }

        $this->stream = null;
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}
