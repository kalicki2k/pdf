<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class FormFieldTextAppearanceStream extends IndirectObject
{
    /** @var list<string> */
    private array $encodedLines;

    /**
     * @param list<string> $lines
     */
    public function __construct(
        int $id,
        private readonly float $width,
        private readonly float $height,
        private readonly FontDefinition & IndirectObject $font,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly array $lines,
        private readonly ?Color $textColor = null,
    ) {
        parent::__construct($id);

        $this->encodedLines = array_map(
            fn (string $line): string => $this->font->encodeText($line),
            $this->visibleLines(),
        );
        $this->updateUnicodeFontWidths();
    }

    public function render(): string
    {
        $content = implode(PHP_EOL, $this->buildContentLines());

        $dictionary = new DictionaryType([
            'Type' => new NameType('XObject'),
            'Subtype' => new NameType('Form'),
            'FormType' => 1,
            'BBox' => new ArrayType([0, 0, $this->width, $this->height]),
            'Resources' => new DictionaryType([
                'Font' => new DictionaryType([
                    $this->fontResourceName => new ReferenceType($this->font),
                ]),
            ]),
            'Length' => strlen($content),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $content . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    /**
     * @return list<string>
     */
    private function buildContentLines(): array
    {
        $lines = [
            'q',
            '1 g',
            '0 G',
            '1 w',
            sprintf('0 0 %s %s re', $this->format($this->width), $this->format($this->height)),
            'B',
        ];

        if ($this->encodedLines === []) {
            $lines[] = 'Q';

            return $lines;
        }

        $paddingX = 2.5;
        $paddingY = 2.5;
        $leading = max($this->fontSize * 1.2, $this->fontSize + 1.0);
        $startY = max($paddingY, $this->height - $paddingY - $this->fontSize);

        $lines[] = 'BT';
        $lines[] = sprintf('/%s %d Tf', $this->fontResourceName, $this->fontSize);
        $lines[] = $this->textColor?->renderNonStrokingOperator() ?? '0 g';
        $lines[] = sprintf('%s TL', $this->format($leading));
        $lines[] = sprintf('%s %s Td', $this->format($paddingX), $this->format($startY));

        foreach ($this->encodedLines as $index => $line) {
            if ($index > 0) {
                $lines[] = 'T*';
            }

            $lines[] = $line . ' Tj';
        }

        $lines[] = 'ET';
        $lines[] = 'Q';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function visibleLines(): array
    {
        $lineHeight = max($this->fontSize * 1.2, $this->fontSize + 1.0);
        $maxLines = max(1, (int) floor(($this->height - 5.0) / $lineHeight));

        return array_slice($this->lines, 0, $maxLines);
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function updateUnicodeFontWidths(): void
    {
        if (
            !$this->font instanceof UnicodeFont
            || $this->font->descendantFont->cidToGidMap === null
            || $this->font->descendantFont->fontDescriptor === null
        ) {
            return;
        }

        $fontParser = new OpenTypeFontParser($this->font->descendantFont->fontDescriptor->fontFile->data);
        $widths = [];

        foreach ($this->font->getCodePointMap() as $cid => $codePointHex) {
            $utf16 = hex2bin($codePointHex);

            if ($utf16 === false) {
                continue;
            }

            $character = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $widths[$cid] = $fontParser->getAdvanceWidthForGlyphId($glyphId);
        }

        $this->font->descendantFont->setWidths($widths);
    }
}
