<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
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
        $buffer = new StringPdfOutput();
        $this->write($buffer);

        return $buffer->contents();
    }

    public function write(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary()->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeMapDataTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function writeMapDataTo(PdfOutput $output): void
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

    private function dictionary(): DictionaryType
    {
        return new DictionaryType([
            'Length' => $this->mapLength(),
        ]);
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
