<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use Kalle\Pdf\Internal\Object\StreamIndirectObject;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\Render\PdfOutput;

final class ToUnicodeCMap extends StreamIndirectObject
{
    public function __construct(
        int $id,
        private readonly UnicodeGlyphMap $glyphMap,
    ) {
        parent::__construct($id);
    }

    protected function streamDictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Length' => $length,
        ]);
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->writeLines($output, $this->buildCMapLines());
    }

    /**
     * @return list<string>
     */
    private function buildCMapLines(): array
    {
        $lines = [
            '/CIDInit /ProcSet findresource begin',
            '12 dict begin',
            'begincmap',
            '/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def',
            '/CMapName /Adobe-Identity-UCS def',
            '/CMapType 2 def',
            '1 begincodespacerange',
            '<0000> <FFFF>',
            'endcodespacerange',
        ];

        $map = $this->glyphMap->getCodeMap();

        if ($map !== []) {
            $lines[] = count($map) . ' beginbfchar';

            foreach ($map as $code => $character) {
                $unicode = strtoupper(bin2hex(mb_convert_encoding($character, 'UTF-16BE', 'UTF-8')));
                $lines[] = "<$code> <$unicode>";
            }

            $lines[] = 'endbfchar';
        }

        $lines[] = 'endcmap';
        $lines[] = 'CMapName currentdict /CMap defineresource pop';
        $lines[] = 'end';
        $lines[] = 'end';

        return $lines;
    }
}
