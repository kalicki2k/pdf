<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class CidFont extends IndirectObject
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

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Font'),
            'Subtype' => new Name($this->subtype),
            'BaseFont' => new Name($this->baseFont),
            'CIDSystemInfo' => new Dictionary([
                'Registry' => "($this->registry)",
                'Ordering' => "($this->ordering)",
                'Supplement' => $this->supplement,
            ]),
        ]);

        if ($this->fontDescriptor !== null) {
            $dictionary->add('FontDescriptor', new Reference($this->fontDescriptor));
        }

        if ($this->cidToGidMap !== null) {
            $dictionary->add('CIDToGIDMap', new Reference($this->cidToGidMap));
        }

        $dictionary->add('DW', $this->defaultWidth);

        if ($this->widths !== []) {
            $widthEntries = [];

            foreach ($this->widths as $cid => $width) {
                $widthEntries[] = hexdec($cid);
                $widthEntries[] = new ArrayValue([$width]);
            }

            $dictionary->add('W', new ArrayValue($widthEntries));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
