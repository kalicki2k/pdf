<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Form;

use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Font\UnicodeFont;
use Kalle\Pdf\Internal\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Internal\Object\IndirectObject;
use Kalle\Pdf\Internal\Object\StreamIndirectObject;
use Kalle\Pdf\Internal\Render\PdfOutput;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class FormFieldTextAppearanceStream extends StreamIndirectObject
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
        private readonly UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly array $lines,
        private readonly ?Color $textColor = null,
        private readonly HorizontalAlign $horizontalAlign = HorizontalAlign::LEFT,
        private readonly VerticalAlign $verticalAlign = VerticalAlign::TOP,
        private readonly bool $showsDropdownIndicator = false,
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

    protected function streamDictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('XObject'),
            'Subtype' => new NameType('Form'),
            'FormType' => 1,
            'BBox' => new ArrayType([0, 0, $this->width, $this->height]),
            'Resources' => new DictionaryType([
                'Font' => new DictionaryType([
                    $this->fontResourceName => new ReferenceType($this->font),
                ]),
            ]),
            'Length' => $length,
        ]);
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
        $this->unicodeFontWidthUpdater->update($this->font);
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->writeLines($output, $this->buildContentLines());
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
        $indicatorWidth = $this->showsDropdownIndicator ? min(14.0, max(10.0, $this->height - 4.0)) : 0.0;
        $leading = max($this->fontSize * 1.2, $this->fontSize + 1.0);
        $availableHeight = max(0.0, $this->height - (2 * $paddingY));
        $contentHeight = $this->fontSize + ($leading * (count($this->encodedLines) - 1));
        $bottomY = match ($this->verticalAlign) {
            VerticalAlign::TOP => max($paddingY, $this->height - $paddingY - $contentHeight),
            VerticalAlign::MIDDLE => $paddingY + max(0.0, ($availableHeight - $contentHeight) / 2),
            VerticalAlign::BOTTOM => $paddingY,
        };
        $startY = $bottomY + $contentHeight - $this->fontSize;
        $availableWidth = max(0.0, $this->width - (2 * $paddingX) - $indicatorWidth);

        if ($this->showsDropdownIndicator) {
            $separatorX = $this->width - $indicatorWidth;
            $arrowCenterX = $separatorX + ($indicatorWidth / 2);
            $arrowCenterY = $this->height / 2;
            $arrowHalfWidth = min(3.5, max(2.5, $indicatorWidth * 0.2));
            $arrowHalfHeight = min(2.5, max(1.5, $this->height * 0.08));

            $lines[] = sprintf(
                '%s %s m',
                $this->format($separatorX),
                $this->format($paddingY),
            );
            $lines[] = sprintf(
                '%s %s l',
                $this->format($separatorX),
                $this->format($this->height - $paddingY),
            );
            $lines[] = 'S';
            $lines[] = '0 g';
            $lines[] = sprintf(
                '%s %s m',
                $this->format($arrowCenterX - $arrowHalfWidth),
                $this->format($arrowCenterY + $arrowHalfHeight),
            );
            $lines[] = sprintf(
                '%s %s l',
                $this->format($arrowCenterX),
                $this->format($arrowCenterY - $arrowHalfHeight),
            );
            $lines[] = sprintf(
                '%s %s l',
                $this->format($arrowCenterX + $arrowHalfWidth),
                $this->format($arrowCenterY + $arrowHalfHeight),
            );
            $lines[] = 'f';
        }

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
}
