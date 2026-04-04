<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;

final class CidToGidMap extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly UnicodeGlyphMap $glyphMap,
        private readonly OpenTypeFontParser $fontParser,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $data = $this->buildMapData();
        $dictionary = new DictionaryType([
            'Length' => strlen($data),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $data . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    private function buildMapData(): string
    {
        $codeMap = $this->glyphMap->getCodeMap();

        if ($codeMap === []) {
            return "\x00\x00";
        }

        $maxCid = max(array_map(
            static fn (string $code): int => (int) hexdec($code),
            array_keys($codeMap),
        ));

        $data = '';

        for ($cid = 0; $cid <= $maxCid; $cid++) {
            $code = strtoupper(sprintf('%04X', $cid));
            $glyphId = isset($codeMap[$code])
                ? $this->fontParser->getGlyphIdForCharacter($codeMap[$code])
                : 0;

            $data .= pack('n', $glyphId);
        }

        return $data;
    }
}
