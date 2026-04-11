<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

/**
 * Carries trailer data required to finish a PDF file.
 */
final readonly class Trailer
{
    public function __construct(
        public int $size,
        public int $rootObjectId,
        public ?int $infoObjectId = null,
        public ?int $encryptObjectId = null,
        public ?string $documentId = null,
    ) {
    }
}
