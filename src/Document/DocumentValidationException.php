<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final class DocumentValidationException extends InvalidArgumentException
{
    public function __construct(
        public readonly DocumentBuildError $error,
        string $message,
    ) {
        parent::__construct($message);
    }
}
