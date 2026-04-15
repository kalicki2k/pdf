<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function sprintf;

use InvalidArgumentException;
use RuntimeException;

final class DocumentBuildException extends RuntimeException
{
    private static ?DocumentBuildHintResolver $hintResolver = null;

    private function __construct(
        string $message,
        public readonly string $profileName,
        public readonly ?string $hint = null,
        ?InvalidArgumentException $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromValidationFailure(Document $document, InvalidArgumentException $previous): self
    {
        $profileName = $document->profile->name();
        $reason = $previous->getMessage();
        $hint = self::hintResolver()->resolve($document, $previous);
        $message = sprintf(
            "Document build failed for profile %s.\nReason: %s%s",
            $profileName,
            $reason,
            $hint !== null ? "\nHint: " . $hint : '',
        );

        return new self($message, $profileName, $hint, $previous);
    }

    private static function hintResolver(): DocumentBuildHintResolver
    {
        return self::$hintResolver ??= new DocumentBuildHintResolver();
    }
}
