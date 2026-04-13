<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use RuntimeException;

use function sprintf;
use function str_contains;

final class DocumentBuildException extends RuntimeException
{
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
        $hint = self::hintFor($document, $reason);
        $message = sprintf(
            "Document build failed for profile %s.\nReason: %s%s",
            $profileName,
            $reason,
            $hint !== null ? "\nHint: " . $hint : '',
        );

        return new self($message, $profileName, $hint, $previous);
    }

    private static function hintFor(Document $document, string $reason): ?string
    {
        if (
            $document->profile->isPdfA()
            && (
                str_contains($reason, 'non-embedded font resource')
                || str_contains($reason, 'requires embedded fonts.')
            )
        ) {
            return 'Use embedded fonts via TextOptions(embeddedFont: ...), table text options, or switch to a non-PDF/A profile.';
        }

        if (
            $document->profile->isPdfA()
            && (
                str_contains($reason, 'extractable Unicode fonts')
                || str_contains($reason, 'requires embedded Unicode fonts.')
            )
        ) {
            return 'Use an embedded Unicode-capable font and render the affected text with embeddedFont instead of a standard PDF font.';
        }

        if (str_contains($reason, 'requires a document language')) {
            return "Set ->language('de-DE') or another valid BCP 47 language tag on the document builder.";
        }

        if (str_contains($reason, 'requires a document title')) {
            return "Set ->title('...') on the document builder before rendering.";
        }

        if (str_contains($reason, 'alternative text for image')) {
            return 'Provide ImageAccessibility with altText, or mark decorative images as decorative.';
        }

        if (
            str_contains($reason, 'StructTreeRoot')
            || str_contains($reason, 'tagged')
            || str_contains($reason, 'requires structured content')
            || str_contains($reason, 'requires structured marked content')
        ) {
            return 'Use beginStructure()/endStructure() for containers and TextOptions(tag: ...) for leaf roles.';
        }

        return null;
    }
}
