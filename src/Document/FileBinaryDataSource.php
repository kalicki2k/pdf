<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final readonly class FileBinaryDataSource implements BinaryDataSource
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

    public function contents(): string
    {
        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read binary data file '$this->path'.");
        }

        return $contents;
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
}
