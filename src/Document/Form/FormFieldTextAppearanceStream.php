<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class FormFieldTextAppearanceStream extends IndirectObject
{
    /** @var list<string> */
    private array $encodedLines;

    /** @var list<float> */
    private array $lineWidths;

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
        private readonly HorizontalAlign $horizontalAlign = HorizontalAlign::LEFT,
        private readonly VerticalAlign $verticalAlign = VerticalAlign::TOP,
    ) {
        parent::__construct($id);

        $visibleLines = $this->visibleLines();

        $this->encodedLines = array_map(
            fn (string $line): string => $this->font->encodeText($line),
            $visibleLines,
        );
        $this->lineWidths = array_map(
            fn (string $line): float => $this->font->measureTextWidth($line, $this->fontSize),
            $visibleLines,
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
        $availableHeight = max(0.0, $this->height - (2 * $paddingY));
        $contentHeight = $this->fontSize + ($leading * (count($this->encodedLines) - 1));
        $bottomY = match ($this->verticalAlign) {
            VerticalAlign::TOP => max($paddingY, $this->height - $paddingY - $contentHeight),
            VerticalAlign::MIDDLE => $paddingY + max(0.0, ($availableHeight - $contentHeight) / 2),
            VerticalAlign::BOTTOM => $paddingY,
        };
        $startY = $bottomY + $contentHeight - $this->fontSize;
        $availableWidth = max(0.0, $this->width - (2 * $paddingX));

        $lines[] = 'BT';
        $lines[] = sprintf('/%s %d Tf', $this->fontResourceName, $this->fontSize);
        $lines[] = $this->textColor?->renderNonStrokingOperator() ?? '0 g';

        foreach ($this->encodedLines as $index => $line) {
            $lineWidth = $this->lineWidths[$index] ?? 0.0;
            $x = match ($this->horizontalAlign) {
                HorizontalAlign::LEFT, HorizontalAlign::JUSTIFY => $paddingX,
                HorizontalAlign::CENTER => $paddingX + max(0.0, ($availableWidth - $lineWidth) / 2),
                HorizontalAlign::RIGHT => max($paddingX, $this->width - $paddingX - $lineWidth),
            };
            $y = $startY - ($index * $leading);

            $lines[] = sprintf(
                '1 0 0 1 %s %s Tm',
                $this->format($x),
                $this->format($y),
            );
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
