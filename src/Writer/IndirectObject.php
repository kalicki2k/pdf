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

    public static function plain(int $objectId, string $contents, bool $encryptable = true): self
    {
        return new self($objectId, $contents, $encryptable);
    }

    public static function stream(
        int $objectId,
        string $streamDictionaryContents,
        string $streamContents,
        bool $encryptable = true,
    ): self {
        return new self(
            objectId: $objectId,
            contents: $streamDictionaryContents . "\nstream\n" . $streamContents . 'endstream',
            encryptable: $encryptable,
            streamDictionaryContents: $streamDictionaryContents,
            streamContents: $streamContents,
        );
    }

    public function __construct(
        public int $objectId,
        public string $contents,
        public bool $encryptable = true,
        ?string $streamDictionaryContents = null,
        ?string $streamContents = null,
    ) {
        if ($streamDictionaryContents !== null || $streamContents !== null) {
            $this->streamDictionaryContents = $streamDictionaryContents;
            $this->streamContents = $streamContents;

            return;
        }

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
