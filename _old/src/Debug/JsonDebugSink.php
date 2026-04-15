<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

use JsonException;
use RuntimeException;

final class JsonDebugSink implements DebugSink
{
    /** @var resource */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new RuntimeException('Debug output stream must be a valid stream resource.');
        }

        $this->stream = $stream;
    }

    public static function stdout(): self
    {
        $stream = fopen('php://stdout', 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open stdout for debug output.');
        }

        return new self($stream);
    }

    public static function stderr(): self
    {
        $stream = fopen('php://stderr', 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open stderr for debug output.');
        }

        return new self($stream);
    }

    public static function fromPath(string $path): self
    {
        if ($path === '') {
            throw new RuntimeException('Debug output path must not be empty.');
        }

        $stream = fopen($path, 'ab');

        if ($stream === false) {
            throw new RuntimeException("Unable to open debug output file '$path'.");
        }

        return new self($stream);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function log(string $channel, LogLevel $level, string $event, array $context = []): void
    {
        try {
            $record = json_encode([
                'timestamp' => date(DATE_ATOM),
                'channel' => $channel,
                'level' => $level->value,
                'event' => $event,
                'message' => '[' . $channel . '] ' . $event,
                'context' => $context,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode debug event as JSON.', 0, $exception);
        }

        $writtenBytes = fwrite($this->stream, $record . PHP_EOL);

        if ($writtenBytes === false) {
            throw new RuntimeException('Unable to write debug event to stream.');
        }
    }
}
