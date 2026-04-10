<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

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
        if (!is_resource($stream)) {
            throw new RuntimeException('Binary data stream must be a valid resource.');
        }

        $metadata = stream_get_meta_data($stream);

        return new self(
            $metadata['seekable'] === true
                ? new StreamBinaryDataSource($stream, $length, $closeOnDestruct)
                : new OneShotStreamBinaryDataSource($stream, $length, $closeOnDestruct),
        );
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
        return $this->requireRandomAccessSource('length inspection')->length();
    }

    public function slice(int $offset, int $length): string
    {
        return $this->requireRandomAccessSource('random-access slicing')->slice($offset, $length);
    }

    public function segment(int $offset, int $length): self
    {
        $this->requireRandomAccessSource('segment creation');

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

    private function requireRandomAccessSource(string $operation): RandomAccessBinaryDataSource
    {
        if ($this->source instanceof RandomAccessBinaryDataSource) {
            return $this->source;
        }

        throw new RuntimeException(sprintf(
            'Binary data source %s does not support %s.',
            $this->source::class,
            $operation,
        ));
    }
}
