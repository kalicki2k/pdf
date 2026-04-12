<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

/**
 * Represents a prepared indirect PDF object body.
 */
final readonly class IndirectObject
{
    public ?string $streamDictionaryContents;
    public ?string $streamContents;

    public function __construct(
        public int $objectId,
        public string $contents,
        public bool $encryptable = true,
    ) {
        [$this->streamDictionaryContents, $this->streamContents] = $this->parseStreamContents($contents);
    }

    /**
     * @return array{?string, ?string}
     */
    private function parseStreamContents(string $contents): array
    {
        $streamMarker = "\nstream\n";
        $streamOffset = strpos($contents, $streamMarker);

        if ($streamOffset === false) {
            return [null, null];
        }

        $streamStart = $streamOffset + strlen($streamMarker);
        $streamEnd = strrpos($contents, "\nendstream");

        if ($streamEnd === false || $streamEnd < $streamStart) {
            return [null, null];
        }

        return [
            substr($contents, 0, $streamOffset),
            substr($contents, $streamStart, $streamEnd - $streamStart),
        ];
    }
}
