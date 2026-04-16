<?php

declare(strict_types=1);

namespace Kalle\Pdf\Contents;

final readonly class Contents
{
    public static function make(string $stream): self
    {
        return new self($stream);
    }

    private function __construct(
        public string $stream,
    ) {
    }

    public function length(): int
    {
        return strlen($this->stream);
    }
}