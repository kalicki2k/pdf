<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

/**
 * Represents a prepared indirect PDF object body.
 */
final readonly class IndirectObject
{
    public function __construct(
        public int $objectId,
        public string $contents,
    ) {
    }
}
