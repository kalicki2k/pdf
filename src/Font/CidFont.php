<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;

final class CidFont extends DictionaryIndirectObject
{
    /** @var array<string, int> */
    private array $widths;

    /**
     * @param array<string, int> $widths
     */
    public function __construct(
        int $id,
        public readonly string $baseFont,
        private readonly string $subtype = 'CIDFontType2',
        private readonly string $registry = 'Adobe',
        private readonly string $ordering = 'Identity',
        private readonly int $supplement = 0,
        public readonly ?FontDescriptor $fontDescriptor = null,
        public readonly ?CidToGidMap $cidToGidMap = null,
        private readonly int $defaultWidth = 1000,
        array $widths = [],
    ) {
        parent::__construct($id);
        $this->widths = $widths;
    }

    public function getBaseFont(): string
    {
        return $this->baseFont;
    }

    /**
     * @param array<string, int> $widths
     */
    public function setWidths(array $widths): void
    {
        $this->widths = $widths;
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Font'),
            'Subtype' => new NameType($this->subtype),
            'BaseFont' => new NameType($this->baseFont),
            'CIDSystemInfo' => new DictionaryType([
                'Registry' => "($this->registry)",
                'Ordering' => "($this->ordering)",
                'Supplement' => $this->supplement,
            ]),
        ]);

        if ($this->fontDescriptor !== null) {
            $dictionary->add('FontDescriptor', new ReferenceType($this->fontDescriptor));
        }

        if ($this->cidToGidMap !== null) {
            $dictionary->add('CIDToGIDMap', new ReferenceType($this->cidToGidMap));
        }

        $dictionary->add('DW', $this->defaultWidth);

        if ($this->widths !== []) {
            $widthEntries = [];

            foreach ($this->widths as $cid => $width) {
                $widthEntries[] = hexdec($cid);
                $widthEntries[] = new ArrayType([$width]);
            }

            $dictionary->add('W', new ArrayType($widthEntries));
        }

        return $dictionary;
    }
}
