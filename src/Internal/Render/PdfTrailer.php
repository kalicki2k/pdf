<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

final readonly class PdfTrailer
{
    /**
     * @param array{string, string} $documentId
     */
    public function __construct(
        public int $rootObjectId,
        public ?int $infoObjectId,
        public ?int $encryptObjectId,
        public array $documentId,
    ) {
    }
}
