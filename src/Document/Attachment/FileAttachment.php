<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Attachment;

use InvalidArgumentException;

final readonly class FileAttachment
{
    public function __construct(
        public string $filename,
        public EmbeddedFile $embeddedFile,
        public ?string $description = null,
        public ?AssociatedFileRelationship $associatedFileRelationship = null,
    ) {
        if ($this->filename === '') {
            throw new InvalidArgumentException('Attachment filename must not be empty.');
        }
    }
}
