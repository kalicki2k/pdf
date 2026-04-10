<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Object\StreamIndirectObject;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Style\Color;

final class FormFieldListBoxAppearanceStream extends StreamIndirectObject
{
    /** @var list<string> */
    private array $encodedLines;

    /** @var list<int> */
    private array $selectedVisibleLineIndexes;

    /**
     * @param array<string, string> $options
     * @param list<string> $selectedValues
     */
    public function __construct(
        int $id,
        private readonly float $width,
        private readonly float $height,
        private readonly FontDefinition & IndirectObject $font,
        private readonly UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
        private readonly string $fontResourceName,
        private readonly int $fontSize,
        private readonly array $options,
        private readonly array $selectedValues,
        private readonly ?Color $textColor = null,
    ) {
        parent::__construct($id);

        $visibleOptions = $this->visibleOptions();
        $selectedLookup = array_fill_keys($this->selectedValues, true);
        $this->selectedVisibleLineIndexes = [];
        $this->encodedLines = [];

        foreach ($visibleOptions as $index => [$exportValue, $label]) {
            $this->encodedLines[] = $this->font->encodeText($label);

            if (isset($selectedLookup[$exportValue])) {
                $this->selectedVisibleLineIndexes[] = $index;
            }
        }

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
        $leading = max($this->fontSize * 1.2, $this->fontSize + 1.0);
        $startTop = $this->height - $paddingY;
        $highlightColor = Color::rgb(56, 117, 215);
        $defaultTextColor = $this->textColor?->renderNonStrokingOperator() ?? '0 g';

        foreach ($this->selectedVisibleLineIndexes as $lineIndex) {
            $rowTop = $startTop - ($lineIndex * $leading);
            $rowBottom = max($paddingY, $rowTop - $leading);

            $lines[] = $highlightColor->renderNonStrokingOperator();
            $lines[] = sprintf(
                '%s %s %s %s re',
                $this->format(1.0),
                $this->format($rowBottom),
                $this->format(max(0.0, $this->width - 2.0)),
                $this->format(max(0.0, $rowTop - $rowBottom)),
            );
            $lines[] = 'f';
        }

        $lines[] = 'BT';
        $lines[] = sprintf('/%s %d Tf', $this->fontResourceName, $this->fontSize);

        foreach ($this->encodedLines as $index => $line) {
            $rowTop = $startTop - ($index * $leading);
            $baselineY = $rowTop - $this->fontSize;

            $lines[] = in_array($index, $this->selectedVisibleLineIndexes, true)
                ? '1 g'
                : $defaultTextColor;
            $lines[] = sprintf(
                '1 0 0 1 %s %s Tm',
                $this->format($paddingX),
                $this->format($baselineY),
            );
            $lines[] = $line . ' Tj';
        }

        $lines[] = 'ET';
        $lines[] = 'Q';

        return $lines;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function visibleOptions(): array
    {
        $lineHeight = max($this->fontSize * 1.2, $this->fontSize + 1.0);
        $maxLines = max(1, (int) floor(($this->height - 5.0) / $lineHeight));
        $visibleOptions = [];

        foreach ($this->options as $exportValue => $label) {
            $visibleOptions[] = [$exportValue, $label];
        }

        return array_slice($visibleOptions, 0, $maxLines);
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
}
