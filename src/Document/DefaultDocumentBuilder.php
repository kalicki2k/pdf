<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function implode;

use InvalidArgumentException;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

use function number_format;
use function str_replace;

use Throwable;

class DefaultDocumentBuilder implements DocumentBuilder
{
    /** @var list<Page> */
    private array $pages = [];
    private ?PageSize $currentPageSize = null;
    private string $currentPageContents = '';
    /** @var array<string, PageFont> */
    private array $currentPageFontResources = [];
    private ?Margin $currentPageMargin = null;
    private ?Color $currentPageBackgroundColor = null;
    private ?string $currentPageLabel = null;
    private ?string $currentPageName = null;
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $language = null;
    private ?string $creator = null;
    private ?string $creatorTool = null;
    private ?Profile $profile = null;

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function author(string $author): self
    {
        $clone = clone $this;
        $clone->author = $author;

        return $clone;
    }

    public function subject(string $subject): DocumentBuilder
    {
        $clone = clone $this;
        $clone->subject = $subject;

        return $clone;
    }

    public function language(string $language): DocumentBuilder
    {
        $clone = clone $this;
        $clone->language = $language;

        return $clone;
    }

    public function creator(string $creator): DocumentBuilder
    {
        $clone = clone $this;
        $clone->creator = $creator;

        return $clone;
    }

    public function creatorTool(string $creatorTool): DocumentBuilder
    {
        $clone = clone $this;
        $clone->creatorTool = $creatorTool;

        return $clone;
    }

    public function profile(Profile $profile): DocumentBuilder
    {
        $clone = clone $this;
        $clone->profile = $profile;

        return $clone;
    }

    public function pageSize(PageSize $size): DocumentBuilder
    {
        $clone = clone $this;
        $clone->currentPageSize = $size;

        return $clone;
    }

    public function content(string $content): DocumentBuilder
    {
        $clone = clone $this;
        $clone->currentPageContents = $content;

        return $clone;
    }

    public function text(string $text, ?TextOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= new TextOptions();
        $font = StandardFontDefinition::from($options->fontName);
        $fontEncoding = $font->resolveEncoding(
            ($this->profile ?? Profile::standard())->version(),
            $options->fontEncoding,
        );
        $fontAlias = $clone->fontAliasFor($font->name, $fontEncoding);

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildTextContent(
                $text,
                $options,
                $fontAlias,
                $font,
                ($this->profile ?? Profile::standard())->version(),
            ),
        );

        return $clone;
    }

    public function glyphs(StandardFontGlyphRun $glyphRun, ?TextOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $options ??= new TextOptions(fontName: $glyphRun->fontName);

        if ($options->fontName !== $glyphRun->fontName) {
            throw new InvalidArgumentException(sprintf(
                "Glyph run font '%s' does not match text option font '%s'.",
                $glyphRun->fontName,
                $options->fontName,
            ));
        }

        $font = StandardFontDefinition::from($glyphRun->fontName);
        $fontEncoding = $font->resolveEncoding(
            ($this->profile ?? Profile::standard())->version(),
            $options->fontEncoding,
        );
        $fontAlias = $clone->fontAliasFor($font->name, $fontEncoding, $glyphRun->differences);

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildEncodedTextContent(
                $glyphRun->bytes,
                $options,
                $fontAlias,
                $font,
                $glyphRun->glyphNames,
                $glyphRun->useHexString,
            ),
        );

        return $clone;
    }

    public function newPage(?PageOptions $options = null): DocumentBuilder
    {
        $clone = clone $this;
        $clone->pages[] = $clone->buildCurrentPage();
        $clone->resetCurrentPage($options);

        return $clone;
    }

    public function build(): Document
    {
        return new Document(
            profile: $this->profile ?? Profile::standard(),
            pages: [...$this->pages, $this->buildCurrentPage()],
            title: $this->title,
            author: $this->author,
            subject: $this->subject,
            language: $this->language,
            creator: $this->creator,
            creatorTool: $this->creatorTool,
        );
    }

    public function save(string $path): string
    {
        $output = new FileOutput($path);

        try {
            (new DocumentRenderer())->write($this->build(), $output);
            $output->close();
        } catch (Throwable $throwable) {
            unset($output);

            throw $throwable;
        }

        return $path;
    }

    private function buildCurrentPage(): Page
    {
        return new Page(
            size: $this->currentPageSize ?? PageSize::A4(),
            contents: $this->currentPageContents,
            fontResources: $this->currentPageFontResources,
            margin: $this->currentPageMargin,
            backgroundColor: $this->currentPageBackgroundColor,
            label: $this->currentPageLabel,
            name: $this->currentPageName,
        );
    }

    private function resetCurrentPage(?PageOptions $options): void
    {
        $this->currentPageSize = $this->resolvePageSize($options);
        $this->currentPageContents = '';
        $this->currentPageFontResources = [];
        $this->currentPageMargin = $options?->margin;
        $this->currentPageBackgroundColor = $options?->backgroundColor;
        $this->currentPageLabel = $options?->label;
        $this->currentPageName = $options?->name;
    }

    private function resolvePageSize(?PageOptions $options): ?PageSize
    {
        $pageSize = $options?->pageSize;

        if ($pageSize === null) {
            return null;
        }

        return match ($options?->orientation) {
            PageOrientation::LANDSCAPE => $pageSize->landscape(),
            PageOrientation::PORTRAIT => $pageSize->portrait(),
            default => $pageSize,
        };
    }

    private function buildTextContent(
        string $text,
        TextOptions $options,
        string $fontAlias,
        StandardFontDefinition $font,
        float $pdfVersion,
    ): string {
        return $this->buildEncodedTextContent(
            $font->encodeText($text, $pdfVersion, $options->fontEncoding),
            $options,
            $fontAlias,
            $font,
            $options->kerning ? $font->glyphNamesForText($text, $pdfVersion, $options->fontEncoding) : [],
        );
    }

    private function buildEncodedTextContent(
        string $encodedText,
        TextOptions $options,
        string $fontAlias,
        StandardFontDefinition $font,
        array $glyphNames = [],
        bool $useHexString = false,
    ): string
    {
        $lines = [
            'BT',
        ];

        if ($options->color !== null) {
            $lines[] = $this->buildFillColorOperator($options->color);
        }

        $lines = [
            ...$lines,
            '/' . $fontAlias . ' ' . $this->formatNumber($options->fontSize) . ' Tf',
            $this->formatNumber($options->x) . ' ' . $this->formatNumber($options->y) . ' Td',
            $this->buildTextShowOperator($encodedText, $font, $glyphNames, $useHexString),
            'ET',
        ];

        return implode("\n", $lines);
    }

    private function pdfLiteralString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    private function pdfHexString(string $value): string
    {
        return '<' . bin2hex($value) . '>';
    }

    /**
     * @param list<?string> $glyphNames
     */
    private function buildTextShowOperator(
        string $encodedText,
        StandardFontDefinition $font,
        array $glyphNames,
        bool $useHexString,
    ): string {
        $kerningOperator = $this->buildKerningTextOperator($encodedText, $font, $glyphNames);

        if ($kerningOperator !== null) {
            return $kerningOperator;
        }

        return ($useHexString ? $this->pdfHexString($encodedText) : $this->pdfLiteralString($encodedText)) . ' Tj';
    }

    /**
     * @param list<?string> $glyphNames
     */
    private function buildKerningTextOperator(
        string $encodedText,
        StandardFontDefinition $font,
        array $glyphNames,
    ): ?string {
        if ($glyphNames === [] || strlen($encodedText) < 2) {
            return null;
        }

        $bytes = str_split($encodedText);

        if (count($bytes) !== count($glyphNames)) {
            return null;
        }

        $parts = [];
        $hasKerning = false;

        foreach ($bytes as $index => $byte) {
            $parts[] = $this->pdfHexString($byte);

            if (!isset($bytes[$index + 1])) {
                continue;
            }

            $leftGlyph = $glyphNames[$index];
            $rightGlyph = $glyphNames[$index + 1];

            if ($leftGlyph === null || $rightGlyph === null) {
                continue;
            }

            $kerning = $font->kerningValue($leftGlyph, $rightGlyph);

            if ($kerning === 0) {
                continue;
            }

            $parts[] = (string) -$kerning;
            $hasKerning = true;
        }

        if (!$hasKerning) {
            return null;
        }

        return '[' . implode(' ', $parts) . '] TJ';
    }

    private function appendPageContent(string $existingContent, string $newContent): string
    {
        if ($existingContent === '') {
            return $newContent;
        }

        return $existingContent . "\n" . $newContent;
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function fontAliasFor(string $fontName, StandardFontEncoding $fontEncoding, array $differences = []): string
    {
        StandardFontDefinition::from($fontName);

        foreach ($this->currentPageFontResources as $alias => $pageFont) {
            if ($pageFont->matches($fontName, $fontEncoding, $differences)) {
                return $alias;
            }
        }

        $alias = 'F' . (count($this->currentPageFontResources) + 1);
        $this->currentPageFontResources[$alias] = new PageFont($fontName, $fontEncoding, $differences);

        return $alias;
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        );

        return match ($color->space) {
            ColorSpace::GRAY => implode(' ', $components) . ' g',
            ColorSpace::RGB => implode(' ', $components) . ' rg',
            ColorSpace::CMYK => implode(' ', $components) . ' k',
        };
    }
}
