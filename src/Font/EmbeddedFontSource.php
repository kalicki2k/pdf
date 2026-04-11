<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;
use RuntimeException;

use function file_get_contents;
use function is_file;

final readonly class EmbeddedFontSource
{
    private function __construct(
        public string $data,
        public ?string $path = null,
    ) {
        if ($data === '') {
            throw new InvalidArgumentException('Embedded font source data must not be empty.');
        }
    }

    public static function fromString(string $data, ?string $path = null): self
    {
        return new self($data, $path);
    }

    public static function fromPath(string $path): self
    {
        if ($path === '' || !is_file($path)) {
            throw new InvalidArgumentException(sprintf(
                "Embedded font path '%s' does not point to a readable file.",
                $path,
            ));
        }

        $data = file_get_contents($path);

        if (!is_string($data)) {
            throw new RuntimeException(sprintf(
                "Unable to read embedded font data from '%s'.",
                $path,
            ));
        }

        return new self($data, $path);
    }
}
