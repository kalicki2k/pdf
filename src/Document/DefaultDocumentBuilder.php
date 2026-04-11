<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function implode;

use InvalidArgumentException;

use Kalle\Pdf\Color\Color;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Font\StandardFontMetrics;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\MappedTextRun;
use Kalle\Pdf\Text\ShapedTextRun;
use Kalle\Pdf\Text\SimpleFontRunMapper;
use Kalle\Pdf\Text\SimpleTextShaper;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\StreamOutput;
use Kalle\Pdf\Writer\StringOutput;

use function mb_ord;

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
    /** @var array<string, ImageSource> */
    private array $currentPageImageResources = [];
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
            && (
                !$font->supportsText($text)
                || $this->containsShapedEmbeddedGlyphIds($shapedLines)
            );

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
                $wrappedLines,
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

    public function image(ImageSource $source, ImagePlacement $placement): DocumentBuilder
    {
        $clone = clone $this;
        $imageAlias = $clone->imageAliasFor($source);
        [$width, $height] = $this->resolveImageDimensions($source, $placement);

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->buildImageContent($imageAlias, $placement->x, $placement->y, $width, $height),
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
        $textFlow = $clone->textFlow();
        $placement = $textFlow->placement($options, $font);
        $availableWidth = $textFlow->availableTextWidthFrom($placement['x'], $options);
        /** @var list<string> $measurableGlyphNames */
        $measurableGlyphNames = array_values(array_filter(
            $glyphRun->glyphNames,
            static fn (?string $glyphName): bool => $glyphName !== null,
        ));
        $glyphWidth = StandardFontMetrics::measureGlyphNamesWidth($font->name, $measurableGlyphNames, $options->fontSize)
            ?? 0.0;
        $alignedX = $this->alignedLineX($options->align, $placement['x'], max($availableWidth - $glyphWidth, 0.0));

        $clone->currentPageContents = $this->appendPageContent(
            $clone->currentPageContents,
            $this->textBlockBuilder()->build(
                encodedText: $glyphRun->bytes,
                options: $options,
                x: $alignedX,
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
            imageResources: $this->currentPageImageResources,
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
        $this->currentPageImageResources = [];
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
     * @param list<string> $wrappedLines
     * @param list<list<ShapedTextRun>> $shapedLines
     */
    private function buildWrappedTextContent(
        array $wrappedLines,
        array $shapedLines,
        TextOptions $options,
        TextFlow $textFlow,
        float $x,
        float $y,
        string $fontAlias,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        ?PageFont $embeddedPageFont,
        bool $useHexString,
        float $pdfVersion,
    ): string {
        $contents = [];
        $availableWidth = $textFlow->availableTextWidthFrom($x, $options);

        foreach ($shapedLines as $index => $lineRuns) {
            if ($lineRuns === []) {
                continue;
            }

            $runY = $y - ($textFlow->lineHeight($options) * $index);
            $isFirstLineOfParagraph = $this->isFirstLineOfParagraph($wrappedLines, $index);
            $lineBaseX = $textFlow->lineX($x, $options, $isFirstLineOfParagraph);
            $mappedRuns = [];

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

                $mappedRuns[] = $mappedRun;
            }

            if ($mappedRuns === []) {
                continue;
            }

            $lineWidth = $this->lineWidth($mappedRuns);
            $lineIndent = $isFirstLineOfParagraph
                ? max($options->firstLineIndent, 0.0)
                : max($options->hangingIndent, 0.0);
            $lineAvailableWidth = $options->width !== null
                ? max($availableWidth - $lineIndent, 0.0)
                : $textFlow->availableTextWidthFrom($lineBaseX, $options);
            $remainingWidth = max($lineAvailableWidth - $lineWidth, 0.0);
            $runX = $this->alignedLineX($options->align, $lineBaseX, $remainingWidth);

            if ($options->align === TextAlign::JUSTIFY && $this->shouldJustifyLine($wrappedLines, $index)) {
                $mappedRuns = $this->justifyMappedRuns($mappedRuns, $remainingWidth, $options);
            }

            foreach ($mappedRuns as $mappedRun) {
                $contents[] = $this->textBlockBuilder()->build(
                    $mappedRun->encodedText,
                    $options,
                    $runX,
                    $runY,
                    $fontAlias,
                    $font,
                    $mappedRun->glyphNames,
                    $mappedRun->textAdjustments,
                    $mappedRun->positionedFragments,
                    $mappedRun->useHexString,
                );
                $runX += $mappedRun->width;
            }
        }

        return implode("\n", $contents);
    }

    /**
     * @param list<MappedTextRun> $mappedRuns
     */
    private function lineWidth(array $mappedRuns): float
    {
        $width = 0.0;

        foreach ($mappedRuns as $mappedRun) {
            $width += $mappedRun->width;
        }

        return $width;
    }

    private function alignedLineX(TextAlign $align, float $x, float $remainingWidth): float
    {
        return match ($align) {
            TextAlign::CENTER => $x + ($remainingWidth / 2),
            TextAlign::RIGHT => $x + $remainingWidth,
            default => $x,
        };
    }

    /**
     * @param list<string> $wrappedLines
     */
    private function shouldJustifyLine(array $wrappedLines, int $lineIndex): bool
    {
        if (!isset($wrappedLines[$lineIndex + 1])) {
            return false;
        }

        return $wrappedLines[$lineIndex] !== '' && $wrappedLines[$lineIndex + 1] !== '';
    }

    /**
     * @param list<string> $wrappedLines
     */
    private function isFirstLineOfParagraph(array $wrappedLines, int $lineIndex): bool
    {
        if ($lineIndex === 0) {
            return true;
        }

        return ($wrappedLines[$lineIndex - 1] ?? '') === '';
    }

    /**
     * @param list<MappedTextRun> $mappedRuns
     * @return list<MappedTextRun>
     */
    private function justifyMappedRuns(array $mappedRuns, float $remainingWidth, TextOptions $options): array
    {
        if ($remainingWidth <= 0.0) {
            return $mappedRuns;
        }

        $spaceCount = 0;

        foreach ($mappedRuns as $mappedRun) {
            $spaceCount += substr_count($mappedRun->text, ' ');
        }

        if ($spaceCount === 0) {
            return $mappedRuns;
        }

        $extraSpaceAdjustment = (int) round(-($remainingWidth / $spaceCount) / $options->fontSize * 1000);
        $justifiedRuns = [];

        foreach ($mappedRuns as $mappedRun) {
            if ($mappedRun->positionedFragments !== []) {
                $justifiedRuns[] = $mappedRun;

                continue;
            }

            $adjustments = $mappedRun->textAdjustments;
            $characters = preg_split('//u', $mappedRun->text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($mappedRun->text);

            foreach ($characters as $index => $character) {
                if ($character !== ' ' || !isset($characters[$index + 1])) {
                    continue;
                }

                $adjustments[$index] = ($adjustments[$index] ?? 0) + $extraSpaceAdjustment;
            }

            $justifiedRuns[] = new MappedTextRun(
                script: $mappedRun->script,
                text: $mappedRun->text,
                encodedText: $mappedRun->encodedText,
                glyphNames: $mappedRun->glyphNames,
                codePoints: $mappedRun->codePoints,
                embeddedGlyphs: $mappedRun->embeddedGlyphs,
                textAdjustments: array_values($adjustments),
                positionedFragments: $mappedRun->positionedFragments,
                useHexString: $mappedRun->useHexString,
                width: $mappedRun->width + ($remainingWidth * (substr_count($mappedRun->text, ' ') / $spaceCount)),
            );
        }

        return $justifiedRuns;
    }

    private function appendPageContent(string $existingContent, string $newContent): string
    {
        if ($existingContent === '') {
            return $newContent;
        }

        return $existingContent . "\n" . $newContent;
    }

    private function buildImageContent(string $imageAlias, float $x, float $y, float $width, float $height): string
    {
        return implode("\n", [
            'q',
            $this->formatNumber($width) . ' 0 0 ' . $this->formatNumber($height) . ' '
            . $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' cm',
            '/' . $imageAlias . ' Do',
            'Q',
        ]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveImageDimensions(ImageSource $source, ImagePlacement $placement): array
    {
        if ($placement->width !== null && $placement->height !== null) {
            return [$placement->width, $placement->height];
        }

        if ($placement->width !== null) {
            return [
                $placement->width,
                $placement->width * ($source->height / $source->width),
            ];
        }

        if ($placement->height !== null) {
            return [
                $placement->height * ($source->width / $source->height),
                $placement->height,
            ];
        }

        return [(float) $source->width, (float) $source->height];
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
     * @return list<list<ShapedTextRun>>
     */
    private function shapeWrappedTextLines(
        array $lines,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): array {
        $shapedLines = [];

        foreach ($lines as $line) {
            $shapedLines[] = $line === ''
                ? []
                : $this->textShaper()->shape($line, $options->baseDirection, $font);
        }

        return $shapedLines;
    }

    /**
     * @param list<list<ShapedTextRun>> $shapedLines
     */
    private function containsShapedEmbeddedGlyphIds(array $shapedLines): bool
    {
        foreach ($shapedLines as $lineRuns) {
            foreach ($lineRuns as $run) {
                foreach ($run->glyphs as $glyph) {
                    if ($glyph->glyphId !== null) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param list<list<ShapedTextRun>> $shapedLines
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

    private function imageAliasFor(ImageSource $source): string
    {
        $sourceKey = $source->key();

        foreach ($this->currentPageImageResources as $alias => $imageSource) {
            if ($imageSource->key() === $sourceKey) {
                return $alias;
            }
        }

        $alias = 'Im' . (count($this->currentPageImageResources) + 1);
        $this->currentPageImageResources[$alias] = $source;

        return $alias;
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

}
