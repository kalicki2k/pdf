<?php

declare(strict_types=1);

namespace Kalle\Pdf\Debug;

use RuntimeException;

final class TextDebugSink implements DebugSink
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
        $line = date(DATE_ATOM)
            . ' level=' . $level->value
            . ' channel=' . $channel
            . ' event=' . $event
            . $this->formatContext($context);

        $writtenBytes = fwrite($this->stream, $line . PHP_EOL);

        if ($writtenBytes === false) {
            throw new RuntimeException('Unable to write debug event to stream.');
        }
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $parts = [];

        foreach ($context as $key => $value) {
            $parts[] = $key . '=' . $this->stringifyValue($value);
        }

        return ' ' . implode(' ', $parts);
    }

    private function stringifyValue(string | int | float | bool | null $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => '"' . addcslashes($value, "\\\"\n\r\t") . '"',
            default => (string) $value,
        };
    }
}
