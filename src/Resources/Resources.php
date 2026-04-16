<?php

declare(strict_types=1);

namespace Kalle\Pdf\Resources;

final readonly class Resources
{
    /**
     * @param array<string, int> $fontObjectIds
     */
    public static function make(array $fontObjectIds = []): self
    {
        return new self($fontObjectIds);
    }

    /**
     * @param array<string, int> $fontObjectIds
     */
    private function __construct(
        public array $fontObjectIds = [],
    ) {
    }
}