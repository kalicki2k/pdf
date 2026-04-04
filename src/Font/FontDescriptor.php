<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class FontDescriptor extends IndirectObject
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

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('FontDescriptor'),
            'FontName' => new Name($this->fontName),
            'Flags' => $this->flags,
            'FontBBox' => new ArrayValue($this->fontBBox),
            'ItalicAngle' => $this->italicAngle,
            'Ascent' => $this->ascent,
            'Descent' => $this->descent,
            'CapHeight' => $this->capHeight,
            'StemV' => $this->stemV,
            $this->getFontFileKey() => new Reference($this->fontFile),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
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
