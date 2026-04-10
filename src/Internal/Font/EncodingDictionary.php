<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\RawType;

final class EncodingDictionary extends DictionaryIndirectObject
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

    protected function dictionary(): DictionaryType
    {
        $parts = [];
        $currentCode = null;

        foreach ($this->differences as $code => $glyphName) {
            if ($currentCode === null || $code !== $currentCode + 1) {
                $parts[] = new RawType((string) $code);
            }

            $parts[] = new NameType($glyphName);
            $currentCode = $code;
        }

        return new DictionaryType([
            'Type' => new NameType('Encoding'),
            'BaseEncoding' => new NameType($this->baseEncoding),
            'Differences' => new ArrayType($parts),
        ]);
    }
}
