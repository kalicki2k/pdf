<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

final class ToUnicodeCMap extends IndirectObject implements EncryptableIndirectObject
{
    public function __construct(
        int $id,
        private readonly UnicodeGlyphMap $glyphMap,
    ) {
        parent::__construct($id);
    }

    protected function writeObject(PdfOutput $output): void
    {
        $cmap = $this->buildCMap();

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($cmap))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($cmap);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedCmap = $objectEncryptor->encryptString($this->id, $this->buildCMap());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedCmap))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedCmap);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
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

    private function dictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Length' => $length,
        ]);
    }
}
