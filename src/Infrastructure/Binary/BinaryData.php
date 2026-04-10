<?php

declare(strict_types=1);

namespace Kalle\Pdf\Infrastructure\Binary;

use Kalle\Pdf\Render\PdfOutput;

final class BinaryData
{
    private function __construct(
        private readonly BinaryDataSource $source,
    ) {
    }

    public static function fromString(string $bytes): self
    {
        return new self(new StringBinaryDataSource($bytes));
    }

    public static function fromFile(string $path): self
    {
        return new self(new FileBinaryDataSource($path));
    }

    /**
     * @param resource $stream
     */
    public static function fromStream($stream, ?int $length = null, bool $closeOnDestruct = false): self
    {
        return new self(new StreamBinaryDataSource($stream, $length, $closeOnDestruct));
    }

    public function length(): int
    {
        return $this->source->length();
    }

    public function contents(): string
    {
        return $this->source->contents();
    }

    public function writeTo(PdfOutput $output): void
    {
        $this->source->writeTo($output);
    }

    public function __destruct()
    {
        $this->source->close();
    }
}
