<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

use InvalidArgumentException;
use RuntimeException;

final class AtomicFilePdfOutput implements PdfOutput
{
    /** @var resource|null */
    private $stream;

    private readonly StreamPdfOutput $streamOutput;
    private bool $committed = false;

    public function __construct(private readonly string $targetPath)
    {
        if ($targetPath === '') {
            throw new InvalidArgumentException('PDF output path must not be empty.');
        }

        $temporaryPath = @tempnam(dirname($targetPath), 'pdf-');

        if ($temporaryPath === false) {
            throw new RuntimeException("Unable to create a temporary PDF output file for '$targetPath'.");
        }

        $this->temporaryPath = $temporaryPath;
        $stream = @fopen($temporaryPath, 'wb');

        if ($stream === false) {
            @unlink($temporaryPath);

            throw new RuntimeException("Unable to open temporary PDF output file '$temporaryPath' for writing.");
        }

        $this->stream = $stream;
        $this->streamOutput = new StreamPdfOutput($stream);
    }

    private readonly string $temporaryPath;

    public function write(string $bytes): void
    {
        $this->streamOutput->write($bytes);
    }

    public function offset(): int
    {
        return $this->streamOutput->offset();
    }

    public function commit(): void
    {
        if ($this->committed) {
            return;
        }

        if (is_resource($this->stream)) {
            if (!fflush($this->stream)) {
                throw new RuntimeException("Unable to flush temporary PDF output file '$this->temporaryPath'.");
            }

            if (!fclose($this->stream)) {
                throw new RuntimeException("Unable to close temporary PDF output file '$this->temporaryPath'.");
            }

            $this->stream = null;
        }

        if (!@rename($this->temporaryPath, $this->targetPath)) {
            throw new RuntimeException("Unable to move temporary PDF output file to '$this->targetPath'.");
        }

        $this->committed = true;
    }

    public function discard(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }

        if (!$this->committed && is_file($this->temporaryPath)) {
            @unlink($this->temporaryPath);
        }
    }

    public function __destruct()
    {
        $this->discard();
    }
}
