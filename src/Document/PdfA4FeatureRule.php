<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final readonly class PdfA4FeatureRule
{
    public function __construct(
        public bool $allowed,
        public bool $required,
        public string $note,
    ) {
    }
}
