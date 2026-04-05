<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\NameType;

final class EncodingDictionary extends IndirectObject
{
    /**
     * @param array<int, string> $differences
     */
    public function __construct(
        int $id,
        private readonly string $baseEncoding,
        private readonly array $differences,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $parts = [];
        $currentCode = null;

        foreach ($this->differences as $code => $glyphName) {
            if ($currentCode === null || $code !== $currentCode + 1) {
                $parts[] = (string) $code;
            }

            $parts[] = (new NameType($glyphName))->render();
            $currentCode = $code;
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . '<< /Type /Encoding /BaseEncoding /' . $this->baseEncoding . ' /Differences [' . implode(' ', $parts) . '] >>' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
