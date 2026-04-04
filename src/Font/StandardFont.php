<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Utilities\PdfStringEscaper;

final class StandardFont extends IndirectObject implements FontDefinition
{
    public function __construct(
        int                     $id,
        public readonly string $baseFont,
        private readonly string $subtype,
        private readonly string $encoding,
        private readonly float  $version,
    ) {
        parent::__construct($id);
        $this->validate();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBaseFont(): string
    {
        return $this->baseFont;
    }

    public function supportsText(string $text): bool
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        return $roundTrip === $text;
    }

    public function encodeText(string $text): string
    {
        if (!$this->supportsText($text)) {
            throw new InvalidArgumentException("Text cannot be encoded with font '$this->baseFont'.");
        }

        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return '(' . PdfStringEscaper::escape($encoded) . ')';
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
            'ZapfDingbatsEncoding',
        ];

        $symbolFonts = ['Symbol'];
        $zapfDingbatsFonts = ['ZapfDingbats'];

        if ($this->version === 1.0 && !in_array($this->encoding, $allowedEncodings10, true)) {
            throw new InvalidArgumentException("Encoding '$this->encoding' is not allowed in PDF 1.0.");
        }

        if ($this->encoding === 'SymbolEncoding' && !in_array($this->baseFont, $symbolFonts, true)) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not compatible with 'SymbolEncoding'.");
        }

        if ($this->encoding === 'ZapfDingbatsEncoding' && !in_array($this->baseFont, $zapfDingbatsFonts, true)) {
            throw new InvalidArgumentException("BaseFont '$this->baseFont' is not compatible with 'ZapfDingbatsEncoding'.");
        }
    }
}
