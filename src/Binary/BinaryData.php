<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;

final readonly class BinaryData
{
    private function __construct(private BinaryDataSource $source)
    {
    }

    public static function fromSource(BinaryDataSource $source): self
    {
        return new self($source);
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

    public static function concatenate(self ...$segments): self
    {
        if ($segments === []) {
            return self::fromString('');
        }

        return new self(new ConcatenatedBinaryDataSource(array_values($segments)));
    }

    public function length(): int
    {
        return $this->source->length();
    }

    public function slice(int $offset, int $length): string
    {
        return $this->source->slice($offset, $length);
    }

    public function segment(int $offset, int $length): self
    {
        return new self(new SlicedBinaryDataSource($this, $offset, $length));
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
