<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class FormFieldListBoxAppearanceStream extends IndirectObject implements EncryptableIndirectObject
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

    public function render(): string
    {
        $content = $this->content();

        return $this->id . ' 0 obj' . PHP_EOL
            . $this->dictionary(strlen($content))->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $content . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContent = $objectEncryptor->encryptString($this->id, $this->content());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedContent))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContent);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function content(): string
    {
        return implode(PHP_EOL, $this->buildContentLines());
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
        (new UnicodeFontWidthUpdater())->update($this->font);
    }

    private function dictionary(int $length): DictionaryType
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
}
