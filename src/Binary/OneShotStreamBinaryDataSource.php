<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final class OneShotStreamBinaryDataSource implements BinaryDataSource
{
    private const READ_CHUNK_BYTES = 8192;

    /** @var resource */
    private $stream;

    /** @var list<resource> */
    private array $streamsToClose = [];

    private bool $isConsumed = false;

    /**
     * @param resource $stream
     */
    public function __construct(
        $stream,
        private readonly ?int $length = null,
        bool $closeOnDestruct = false,
    ) {
        if (!is_resource($stream)) {
            throw new RuntimeException('Binary data stream must be a valid resource.');
        }

        if ($this->length !== null && $this->length < 0) {
            throw new RuntimeException('Binary data stream length must not be negative.');
        }

        $metadata = stream_get_meta_data($stream);
        $mode = (string) $metadata['mode'];

        if (!str_contains($mode, 'r') && !str_contains($mode, '+')) {
            throw new RuntimeException('Binary data stream must be readable.');
        }

        $this->stream = $stream;

        if ($closeOnDestruct) {
            $this->streamsToClose[] = $stream;
        }
    }

    public function writeTo(PdfOutput $output): void
    {
        if ($this->isConsumed) {
            throw new RuntimeException('Unable to replay a non-seekable binary stream after it has been consumed.');
        }

        $writtenBytes = 0;

        while (!feof($this->stream)) {
            $chunk = fread($this->stream, self::READ_CHUNK_BYTES);

            if ($chunk === false) {
                throw new RuntimeException('Unable to read binary data stream.');
            }

            if ($chunk === '') {
                continue;
            }

            $writtenBytes += strlen($chunk);
            $output->write($chunk);
        }

        if ($this->length !== null && $this->length !== $writtenBytes) {
            throw new RuntimeException('Binary data stream length does not match the provided byte length.');
        }

        $this->isConsumed = true;
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
}
