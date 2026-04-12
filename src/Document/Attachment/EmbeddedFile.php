<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Attachment;

use InvalidArgumentException;

final readonly class EmbeddedFile
{
    public function __construct(
        public string $contents,
        public ?string $mimeType = null,
    ) {
        if ($this->mimeType === '') {
            throw new InvalidArgumentException('Embedded file MIME type must not be empty.');
        }
    }

    public function size(): int
    {
        return strlen($this->contents);
    }
}
