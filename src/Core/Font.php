<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use InvalidArgumentException;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;

final class Font extends IndirectObject
{
    public function __construct(
        int                     $id,
        public readonly string $baseFont,
        private readonly string $subtype,
        private readonly string $encoding,
        private readonly float  $version,
    )
    {
        parent::__construct($id);
        $this->validate();
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Font'),
            'Subtype' => new Name($this->subtype),
            'BaseFont' => new Name($this->baseFont),
            'Encoding' => new Name($this->encoding),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    private function validate(): void
    {
        $allowedEncodings10 = [
            'StandardEncoding',
            'ISOLatin1Encoding',
            'SymbolEncoding',
            'ZapfDingbatsEncoding'
        ];

        $symbolFonts = ['Symbol'];
        $zapfDingbatsFonts = ['ZapfDingbats'];

        if ($this->version === 1.0 && !in_array($this->encoding, $allowedEncodings10, true)) {
            throw new InvalidArgumentException("Encoding '$this->encoding' ist in PDF 1.0 nicht erlaubt.");
        }

        if ($this->encoding === 'SymbolEncoding' && !in_array($this->baseFont, $symbolFonts, true)) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' ist nicht kompatibel mit 'SymbolEncoding'.");
        }

        if ($this->encoding === 'ZapfDingbatsEncoding' && !in_array($this->baseFont, $zapfDingbatsFonts, true)) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' ist nicht kompatibel mit 'ZapfDingbatsEncoding'.");
        }
    }
}
