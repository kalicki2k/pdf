<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Attachment;

use InvalidArgumentException;

final readonly class EmbeddedFile
{
    public function __construct(
        public string $contents,
        string|MimeType|null $mimeType = null,
    ) {
        $this->mimeType = $mimeType instanceof MimeType
            ? $mimeType->value
            : $mimeType;

        if ($this->mimeType === '') {
            throw new InvalidArgumentException('Embedded file MIME type must not be empty.');
        }
    }

    public ?string $mimeType;

    public function size(): int
    {
        return strlen($this->contents);
    }
}
