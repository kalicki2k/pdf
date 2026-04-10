<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Internal\PdfType\ArrayType;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\ReferenceType;

final class FontDescriptor extends DictionaryIndirectObject
{
    /**
     * @param list<int|float> $fontBBox
     */
    public function __construct(
        int $id,
        public readonly string $fontName,
        public readonly FontFileStream $fontFile,
        private readonly int $flags = 4,
        private readonly array $fontBBox = [0, -200, 1000, 900],
        private readonly int $italicAngle = 0,
        private readonly int $ascent = 800,
        private readonly int $descent = -200,
        private readonly int $capHeight = 700,
        private readonly int $stemV = 80,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('FontDescriptor'),
            'FontName' => new NameType($this->fontName),
            'Flags' => $this->flags,
            'FontBBox' => new ArrayType($this->fontBBox),
            'ItalicAngle' => $this->italicAngle,
            'Ascent' => $this->ascent,
            'Descent' => $this->descent,
            'CapHeight' => $this->capHeight,
            'StemV' => $this->stemV,
            $this->getFontFileKey() => new ReferenceType($this->fontFile),
        ]);

        return $dictionary;
    }

    private function getFontFileKey(): string
    {
        return match ($this->fontFile->getStreamType()) {
            'FontFile2' => 'FontFile2',
            'FontFile3' => 'FontFile3',
            default => 'FontFile',
        };
    }
}
