<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class FormFieldTextAppearanceStream extends IndirectObject implements EncryptableIndirectObject
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
