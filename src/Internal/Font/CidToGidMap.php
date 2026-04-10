<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use Kalle\Pdf\Internal\Object\StreamIndirectObject;
use Kalle\Pdf\Internal\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

final class CidToGidMap extends StreamIndirectObject
{
    public function __construct(
        int $id,
        private readonly UnicodeGlyphMap $glyphMap,
        private readonly OpenTypeFontParser $fontParser,
    ) {
        parent::__construct($id);
    }

    protected function streamDictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Length' => $length,
        ]);
    }

    /**
     * @param array<string, string> $codeMap
     */
    private function maxCid(array $codeMap): int
    {
        $maxCid = 0;

        foreach (array_keys($codeMap) as $code) {
            $maxCid = max($maxCid, (int) hexdec($code));
        }

        return $maxCid;
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $codeMap = $this->glyphMap->getCodeMap();

        if ($codeMap === []) {
            $output->write("\x00\x00");

            return;
        }

        $maxCid = $this->maxCid($codeMap);

        for ($cid = 0; $cid <= $maxCid; $cid++) {
            $code = strtoupper(sprintf('%04X', $cid));
            $glyphId = isset($codeMap[$code])
                ? $this->fontParser->getGlyphIdForCharacter($codeMap[$code])
                : 0;

            $output->write(pack('n', $glyphId));
        }
    }

    protected function streamLength(): int
    {
        return $this->mapLength();
    }

    private function mapLength(): int
    {
        $codeMap = $this->glyphMap->getCodeMap();

        if ($codeMap === []) {
            return 2;
        }

        return ($this->maxCid($codeMap) + 1) * 2;
    }
}
