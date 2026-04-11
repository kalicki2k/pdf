<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function implode;
use function mb_ord;

use InvalidArgumentException;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\SimpleFontRunMapper;
use Kalle\Pdf\Text\SimpleTextShaper;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\StreamOutput;
use Kalle\Pdf\Writer\StringOutput;

use Throwable;

class DefaultDocumentBuilder implements DocumentBuilder
{
    /** @var list<Page> */
    private array $pages = [];
    private ?PageSize $defaultPageSize = null;
    private ?Margin $defaultPageMargin = null;
    private ?PageSize $currentPageSize = null;
    private string $currentPageContents = '';
    /** @var array<string, PageFont> */
    private array $currentPageFontResources = [];
    private ?Margin $currentPageMargin = null;
    private ?float $currentPageCursorY = null;
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
        $clone->defaultPageSize = $size;
        $clone->currentPageSize = $size;

        return $clone;
    }

    public function margin(Margin $margin): DocumentBuilder
    {
        $clone = clone $this;
        $clone->defaultPageMargin = $margin;
        $clone->currentPageMargin = $margin;
        $clone->currentPageCursorY = null;

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
        $font = $options->embeddedFont !== null
            ? EmbeddedFontDefinition::fromSource($options->embeddedFont)
            : StandardFontDefinition::from($options->fontName);
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $wrappedLines = $textFlow->wrapTextLines($text, $options, $font, $placement['x']);
        $shapedLines = $clone->shapeWrappedTextLines($wrappedLines, $options, $font);
        $usesUnicodeEmbeddedFont = $font instanceof EmbeddedFontDefinition
            && !$font->supportsText($text);

        if ($font instanceof EmbeddedFontDefinition && $usesUnicodeEmbeddedFont && !$font->supportsUnicodeText($text)) {
            throw new InvalidArgumentException(sprintf(
                "Text cannot be encoded with embedded font '%s'.",
                $font->metadata->postScriptName,
            ));
        }

        $fontAlias = $font instanceof EmbeddedFontDefinition
            ? (
                $usesUnicodeEmbeddedFont
                    ? $clone->embeddedUnicodeFontAliasFor($font, $clone->embeddedGlyphsForShapedLines($shapedLines, $font))
                    : $clone->embeddedFontAliasFor($font)
            )
            : $clone->fontAliasFor(
                $font->name,
                $font->resolveEncoding(
                    ($this->profile ?? Profile::standard())->version(),
                    $options->fontEncoding,
                ),
            );
        $embeddedPageFont = $font instanceof EmbeddedFontDefinition
            ? $clone->currentPageFontResources[$fontAlias] ?? null
            : null;

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildWrappedTextContent(
                $shapedLines,
                $options,
                $textFlow,
                $placement['x'],
                $placement['y'],
                $fontAlias,
                $font,
                $embeddedPageFont,
                $usesUnicodeEmbeddedFont,
                ($this->profile ?? Profile::standard())->version(),
            ),
        );
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y'], count($wrappedLines));

        return $clone;
    }

    public function paragraph(string $text, ?TextOptions $options = null): DocumentBuilder
    {
        return $this->text($text, $options);
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
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->textBlockBuilder()->build(
                encodedText: $glyphRun->bytes,
                options: $options,
                x: $placement['x'],
                y: $placement['y'],
                fontAlias: $fontAlias,
                font: $font,
                glyphNames: $glyphRun->glyphNames,
                textAdjustments: [],
                useHexString: $glyphRun->useHexString,
            ),
        );
        $clone->currentPageCursorY = $textFlow->nextCursorY($options, $placement['y']);

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

    public function contents(): string
    {
        $output = new StringOutput();

        new DocumentRenderer()->write($this->build(), $output);

        return $output->contents();
    }

    public function writeToStream($stream): void
    {
        $output = new StreamOutput($stream);

        new DocumentRenderer()->write($this->build(), $output);
    }

    public function writeToFile(string $path): void
    {
        $output = new FileOutput($path);

        try {
            new DocumentRenderer()->write($this->build(), $output);
            $output->close();
        } catch (Throwable $throwable) {
            unset($output);

            throw $throwable;
        }
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
        $this->currentPageMargin = $options !== null
            ? $options->margin ?? $this->defaultPageMargin
            : $this->defaultPageMargin;
        $this->currentPageCursorY = null;
        $this->currentPageBackgroundColor = $options?->backgroundColor;
        $this->currentPageLabel = $options?->label;
        $this->currentPageName = $options?->name;
    }

    private function resolvePageSize(?PageOptions $options): ?PageSize
    {
        $pageSize = $options !== null
            ? $options->pageSize ?? $this->defaultPageSize
            : $this->defaultPageSize;

        if ($pageSize === null) {
            return null;
        }

        return match ($options?->orientation) {
            PageOrientation::LANDSCAPE => $pageSize->landscape(),
            PageOrientation::PORTRAIT => $pageSize->portrait(),
            default => $pageSize,
        };
    }

    /**
     * @param list<list<\Kalle\Pdf\Text\ShapedTextRun>> $shapedLines
     */
    private function buildWrappedTextContent(
        array $shapedLines,
        TextOptions $options,
        TextFlow $textFlow,
        float $x,
        float $y,
        string $fontAlias,
        StandardFontDefinition|EmbeddedFontDefinition $font,
        ?PageFont $embeddedPageFont,
        bool $useHexString,
        float $pdfVersion,
    ): string {
        $contents = [];

        foreach ($shapedLines as $index => $lineRuns) {
            if ($lineRuns === []) {
                continue;
            }

            $runX = $x;
            $runY = $y - ($textFlow->lineHeight($options) * $index);

            foreach ($lineRuns as $run) {
                $mappedRun = $this->fontRunMapper()->map(
                    $run,
                    $font,
                    $options,
                    $pdfVersion,
                    $embeddedPageFont,
                    $useHexString,
                );

                if ($mappedRun->text === '') {
                    continue;
                }

                $contents[] = $this->textBlockBuilder()->build(
                    $mappedRun->encodedText,
                    $options,
                    $runX,
                    $runY,
                    $fontAlias,
                    $font,
                    $mappedRun->glyphNames,
                    $mappedRun->textAdjustments,
                    $mappedRun->useHexString,
                );
                $runX += $mappedRun->width;
            }
        }

        return implode("\n", $contents);
    }

    private function appendPageContent(string $existingContent, string $newContent): string
    {
        if ($existingContent === '') {
            return $newContent;
        }

        return $existingContent . "\n" . $newContent;
    }

    private function textFlow(): TextFlow
    {
        return new TextFlow($this->buildCurrentPage(), $this->currentPageCursorY);
    }

    private function textBlockBuilder(): TextBlockBuilder
    {
        return new TextBlockBuilder();
    }

    private function textShaper(): SimpleTextShaper
    {
        return new SimpleTextShaper();
    }

    private function fontRunMapper(): SimpleFontRunMapper
    {
        return new SimpleFontRunMapper();
    }

    /**
     * @param list<string> $lines
     * @return list<list<\Kalle\Pdf\Text\ShapedTextRun>>
     */
    private function shapeWrappedTextLines(
        array $lines,
        TextOptions $options,
        StandardFontDefinition|EmbeddedFontDefinition $font,
    ): array
    {
        $shapedLines = [];

        foreach ($lines as $line) {
            $shapedLines[] = $line === ''
                ? []
                : $this->textShaper()->shape($line, $options->baseDirection, $font);
        }

        return $shapedLines;
    }

    /**
     * @param list<list<\Kalle\Pdf\Text\ShapedTextRun>> $shapedLines
     * @return list<EmbeddedGlyph>
     */
    private function embeddedGlyphsForShapedLines(array $shapedLines, EmbeddedFontDefinition $font): array
    {
        $embeddedGlyphs = [];

        foreach ($shapedLines as $lineRuns) {
            foreach ($lineRuns as $run) {
                foreach ($run->glyphs as $glyph) {
                    $unicodeCodePoint = $glyph->unicodeCodePoint ?? mb_ord($glyph->character, 'UTF-8');
                    $glyphId = $glyph->glyphId ?? $font->parser->getGlyphIdForCodePoint($unicodeCodePoint);
                    $key = $glyphId . ':' . $unicodeCodePoint;

                    if (isset($embeddedGlyphs[$key])) {
                        continue;
                    }

                    $embeddedGlyphs[$key] = new EmbeddedGlyph(
                        glyphId: $glyphId,
                        unicodeCodePoint: $unicodeCodePoint,
                        unicodeText: $glyph->unicodeText ?? $glyph->character,
                    );
                }
            }
        }

        return array_values($embeddedGlyphs);
    }

    /**
     * @param array<int, string> $differences
     */
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

    private function embeddedFontAliasFor(EmbeddedFontDefinition $font): string
    {
        foreach ($this->currentPageFontResources as $alias => $pageFont) {
            if ($pageFont->matchesEmbedded($font)) {
                return $alias;
            }
        }

        $alias = 'F' . (count($this->currentPageFontResources) + 1);
        $this->currentPageFontResources[$alias] = PageFont::embedded($font);

        return $alias;
    }

    /**
     * @param list<EmbeddedGlyph> $embeddedGlyphs
     */
    private function embeddedUnicodeFontAliasFor(EmbeddedFontDefinition $font, array $embeddedGlyphs): string
    {
        foreach ($this->currentPageFontResources as $alias => $pageFont) {
            if (!$pageFont->matchesEmbedded($font, true)) {
                continue;
            }

            $this->currentPageFontResources[$alias] = $pageFont->withAdditionalEmbeddedGlyphs($embeddedGlyphs);

            return $alias;
        }

        $alias = 'F' . (count($this->currentPageFontResources) + 1);
        $this->currentPageFontResources[$alias] = PageFont::embeddedUnicode($font, $embeddedGlyphs);

        return $alias;
    }

}
