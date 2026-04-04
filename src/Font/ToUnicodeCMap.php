<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\Dictionary;

final class ToUnicodeCMap extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly UnicodeGlyphMap $glyphMap,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $cmap = $this->buildCMap();
        $dictionary = new Dictionary([
            'Length' => strlen($cmap),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $cmap . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    private function buildCMap(): string
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

        return implode(PHP_EOL, $lines);
    }
}
