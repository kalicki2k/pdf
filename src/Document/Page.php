<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Element\DrawImage;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Element\Line;
use Kalle\Pdf\Element\Path;
use Kalle\Pdf\Element\Rectangle;
use Kalle\Pdf\Element\Text;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class Page extends IndirectObject
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const DEFAULT_BOTTOM_MARGIN = 20.0;

    private int $markedContentId = 0;
    /** @var list<LinkAnnotation> */
    private array $annotations = [];
    public Contents $contents;
    public Resources $resources;

    public function __construct(
        public int                $id,
        int                       $contentsId,
        int                       $resourcesId,
        public readonly int       $structParentId,
        private readonly float    $width,
        private readonly float    $height,
        private readonly Document $document,
    ) {
        parent::__construct($this->id);

        $this->contents = new Contents($contentsId);
        $this->resources = new Resources($resourcesId);
    }

    public function addText(
        string $text,
        float $x,
        float $y,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        bool $underline = false,
        bool $strikethrough = false,
        ?string $link = null,
    ): self {
        if ($tag !== null) {
            $this->document->ensureStructureEnabled();
        }

        $font = $this->resolveFont($baseFont);
        $markedContentId = $tag !== null ? $this->markedContentId++ : null;
        $encodedText = $this->encodeText($font, $baseFont, $text);
        $resourceFontName = $this->registerFontResource($font);
        $textWidth = $font->measureTextWidth($text, $size);
        $colorOperator = $color?->renderNonStrokingOperator();
        $graphicsStateName = $opacity !== null ? $this->resources->addOpacity($opacity) : null;

        $this->updateUnicodeFontWidths($font);

        $this->contents->addElement(new Text(
            $markedContentId,
            $encodedText,
            $x,
            $y,
            $resourceFontName,
            $size,
            $textWidth,
            $colorOperator,
            $graphicsStateName,
            $underline,
            $strikethrough,
            $tag,
        ));

        if ($tag !== null && $markedContentId !== null) {
            $this->attachTextToStructure($tag, $markedContentId);
        }

        if ($link !== null && $textWidth > 0.0) {
            $this->addLink(
                $x,
                $y - ($size * 0.2),
                $textWidth,
                $size,
                $link,
            );
        }

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addParagraph(
        string|array $text,
        float $x,
        float $y,
        float $maxWidth,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $bottomMargin = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        TextAlign $align = TextAlign::LEFT,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): self {
        $lineHeight ??= $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin ??= self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        if ($maxLines !== null && $maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        $runs = $this->normalizeTextRuns($text, $color, $opacity);
        $lines = $this->applyOverflowToLines(
            $this->wrapRunsIntoLines($runs, $baseFont, $size, $maxWidth),
            $baseFont,
            $size,
            $maxWidth,
            $maxLines,
            $overflow,
        );
        $page = $this;
        $currentY = $y;
        $topMargin = $this->height - $y;

        foreach ($lines as $line) {
            if ($currentY < $bottomMargin) {
                $page = $this->document->addPage($this->width, $this->height);
                $currentY = $this->height - $topMargin;
            }

            if ($line['segments'] === []) {
                $currentY -= $lineHeight;
                continue;
            }

            $cursorX = $x + $this->calculateAlignedOffset($line['segments'], $baseFont, $size, $maxWidth, $align, $line['justify']);

            if ($align === TextAlign::JUSTIFY && $line['justify']) {
                $this->renderJustifiedLine($page, $line['segments'], $cursorX, $currentY, $baseFont, $size, $tag, $maxWidth);
                $currentY -= $lineHeight;
                continue;
            }

            foreach ($line['segments'] as $segment) {
                $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
                $segmentFont = $this->resolveFont($segmentFontName);

                $page->addText(
                    $segment->text,
                    $cursorX,
                    $currentY,
                    $segmentFontName,
                    $size,
                    $tag,
                    $segment->color,
                    $segment->opacity,
                    $segment->underline,
                    $segment->strikethrough,
                    $segment->link,
                );
                $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
            }

            $currentY -= $lineHeight;
        }

        return $page;
    }

    public function textFrame(
        float $x,
        float $y,
        float $width,
        float $bottomMargin = self::DEFAULT_BOTTOM_MARGIN,
    ): TextFrame {
        return new TextFrame($this, $x, $y, $width, $bottomMargin);
    }

    public function path(): PathBuilder
    {
        return new PathBuilder($this);
    }

    public function addLine(
        float $startX,
        float $startY,
        float $endX,
        float $endY,
        float $width = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): self {
        if ($width <= 0) {
            throw new InvalidArgumentException('Line width must be greater than zero.');
        }

        $colorOperator = $color?->renderStrokingOperator();
        $graphicsStateName = $opacity !== null ? $this->resources->addOpacity($opacity) : null;

        $this->contents->addElement(new Line(
            $startX,
            $startY,
            $endX,
            $endY,
            $width,
            $colorOperator,
            $graphicsStateName,
        ));

        return $this;
    }

    public function addRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($width <= 0) {
            throw new InvalidArgumentException('Rectangle width must be greater than zero.');
        }

        if ($height <= 0) {
            throw new InvalidArgumentException('Rectangle height must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Rectangle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Rectangle requires either a stroke or a fill.');
        }

        $graphicsStateName = $opacity !== null ? $this->resources->addOpacity($opacity) : null;

        $this->contents->addElement(new Rectangle(
            $x,
            $y,
            $width,
            $height,
            $strokeWidth,
            $strokeColor?->renderStrokingOperator(),
            $fillColor?->renderNonStrokingOperator(),
            $graphicsStateName,
        ));

        return $this;
    }

    public function addCircle(
        float $centerX,
        float $centerY,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($radius <= 0) {
            throw new InvalidArgumentException('Circle radius must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Circle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Circle requires either a stroke or a fill.');
        }

        $controlOffset = $radius * 0.5522847498307936;

        $path = $this->path()
            ->moveTo($centerX, $centerY + $radius)
            ->curveTo(
                $centerX + $controlOffset,
                $centerY + $radius,
                $centerX + $radius,
                $centerY + $controlOffset,
                $centerX + $radius,
                $centerY,
            )
            ->curveTo(
                $centerX + $radius,
                $centerY - $controlOffset,
                $centerX + $controlOffset,
                $centerY - $radius,
                $centerX,
                $centerY - $radius,
            )
            ->curveTo(
                $centerX - $controlOffset,
                $centerY - $radius,
                $centerX - $radius,
                $centerY - $controlOffset,
                $centerX - $radius,
                $centerY,
            )
            ->curveTo(
                $centerX - $radius,
                $centerY + $controlOffset,
                $centerX - $controlOffset,
                $centerY + $radius,
                $centerX,
                $centerY + $radius,
            )
            ->close();

        if ($strokeWidth !== null && $fillColor !== null) {
            return $path->fillAndStroke($strokeWidth, $strokeColor, $fillColor, $opacity);
        }

        if ($fillColor !== null) {
            return $path->fill($fillColor, $opacity);
        }

        return $path->stroke($strokeWidth, $strokeColor, $opacity);
    }

    public function addLink(
        float $x,
        float $y,
        float $width,
        float $height,
        string $url,
    ): self {
        if ($width <= 0) {
            throw new InvalidArgumentException('Link width must be greater than zero.');
        }

        if ($height <= 0) {
            throw new InvalidArgumentException('Link height must be greater than zero.');
        }

        if ($url === '') {
            throw new InvalidArgumentException('Link URL must not be empty.');
        }

        $this->annotations[] = new LinkAnnotation(
            $this->document->getUniqObjectId(),
            $this,
            $x,
            $y,
            $width,
            $height,
            $url,
        );

        return $this;
    }

    public function addImage(
        Image $image,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null,
    ): self
    {
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException('Image width must be greater than zero.');
        }

        if ($height !== null && $height <= 0) {
            throw new InvalidArgumentException('Image height must be greater than zero.');
        }

        $width ??= $image->getWidth();
        $height ??= $image->getHeight();

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Image dimensions must be greater than zero.');
        }

        $imageObject = new ImageObject($this->document->getUniqObjectId(), $image);
        $resourceName = $this->resources->addImage($imageObject);
        $this->contents->addElement(new DrawImage($resourceName, $x, $y, $width, $height));

        return $this;
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Page'),
            'Parent' => new Reference($this->document->pages),
            'MediaBox' => new ArrayValue([0, 0, $this->width, $this->height]),
            'Resources' => new Reference($this->resources),
            'Contents' => new Reference($this->contents),
        ]);

        if ($this->markedContentId > 0 && $this->document->hasStructure()) {
            $dictionary->add('StructParents', $this->structParentId);
        }

        if ($this->annotations !== []) {
            $dictionary->add(
                'Annots',
                new ArrayValue(array_map(
                    static fn (LinkAnnotation $annotation): Reference => new Reference($annotation),
                    $this->annotations,
                )),
            );
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * @return list<LinkAnnotation>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        foreach ($this->document->fonts as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                return $registeredFont;
            }
        }

        throw new InvalidArgumentException("Font '$baseFont' is not registered.");
    }

    private function encodeText(FontDefinition $font, string $baseFont, string $text): string
    {
        if (!$font->supportsText($text)) {
            throw new InvalidArgumentException("Font '$baseFont' does not support the provided text.");
        }

        return $font->encodeText($text);
    }

    private function attachTextToStructure(string $tag, int $markedContentId): void
    {
        $this->document->addStructElem($tag, $markedContentId, $this);
    }

    private function registerFontResource(FontDefinition $font): string
    {
        return $this->resources->addFont($font);
    }

    private function updateUnicodeFontWidths(FontDefinition $font): void
    {
        if (
            !$font instanceof UnicodeFont
            || $font->descendantFont->cidToGidMap === null
            || $font->descendantFont->fontDescriptor === null
        ) {
            return;
        }

        $fontParser = new OpenTypeFontParser($font->descendantFont->fontDescriptor->fontFile->data);
        $widths = [];

        foreach ($font->getCodePointMap() as $cid => $codePointHex) {
            $utf16 = hex2bin($codePointHex);

            if ($utf16 === false) {
                throw new InvalidArgumentException("Invalid UTF-16 hex code point '$codePointHex'.");
            }

            $character = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $widths[$cid] = $fontParser->getAdvanceWidthForGlyphId($glyphId);
        }

        $font->descendantFont->setWidths($widths);
    }

    /**
     * @return list<string>
     */
    /**
     * @param string|list<TextSegment> $text
     */
    public function countParagraphLines(
        string|array $text,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): int
    {
        if ($maxLines !== null && $maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        return count($this->applyOverflowToLines(
            $this->wrapRunsIntoLines($this->normalizeTextRuns($text, null, null), $baseFont, $size, $maxWidth),
            $baseFont,
            $size,
            $maxWidth,
            $maxLines,
            $overflow,
        ));
    }

    /**
     * @return list<string>
     */
    private function breakWordToFit(string $word, FontDefinition $font, int $size, float $maxWidth): array
    {
        if ($font->measureTextWidth($word, $size) <= $maxWidth) {
            return [$word];
        }

        $chunks = [];
        $currentChunk = '';

        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $candidate = $currentChunk . $character;

            if ($currentChunk !== '' && $font->measureTextWidth($candidate, $size) > $maxWidth) {
                $chunks[] = $currentChunk;
                $currentChunk = $character;
                continue;
            }

            $currentChunk = $candidate;
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * @param string|array<mixed> $text
     * @return list<TextSegment>
     */
    private function normalizeTextRuns(string|array $text, ?Color $color, ?Opacity $opacity): array
    {
        if (is_string($text)) {
            return [new TextSegment($text, $color, $opacity)];
        }

        $runs = [];

        foreach ($text as $segment) {
            if (!$segment instanceof TextSegment) {
                throw new InvalidArgumentException('Paragraph text arrays must contain only TextSegment instances.');
            }

            $runs[] = $segment->withDefaults($color, $opacity);
        }

        return $runs === [] ? [new TextSegment('', $color, $opacity)] : $runs;
    }

    /**
     * @param list<TextSegment> $runs
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    private function wrapRunsIntoLines(array $runs, string $baseFont, int $size, float $maxWidth): array
    {
        /** @var list<array{segments: array<int, TextSegment>, justify: bool}> $lines */
        $lines = [];
        /** @var list<TextSegment> $currentLine */
        $currentLine = [];
        $currentLineWidth = 0.0;
        $pendingSpace = false;

        foreach ($runs as $run) {
            foreach ($this->tokenizeRun($run) as $token) {
                if ($token['type'] === 'newline') {
                    $lines[] = ['segments' => $currentLine, 'justify' => false];
                    $currentLine = [];
                    $currentLineWidth = 0.0;
                    $pendingSpace = false;
                    continue;
                }

                if ($token['type'] === 'space') {
                    $pendingSpace = $currentLine !== [];
                    continue;
                }

                /** @var TextSegment $wordRun */
                $wordRun = $token['run'];
                $wordFont = $this->resolveFont($this->resolveStyledBaseFont($baseFont, $wordRun));
                $text = ($pendingSpace && $currentLine !== [] ? ' ' : '') . $wordRun->text;
                $textWidth = $wordFont->measureTextWidth($text, $size);

                if ($currentLineWidth + $textWidth <= $maxWidth) {
                    $this->appendRun($currentLine, new TextSegment(
                        $text,
                        $wordRun->color,
                        $wordRun->opacity,
                        $wordRun->link,
                        $wordRun->bold,
                        $wordRun->italic,
                        $wordRun->underline,
                        $wordRun->strikethrough,
                    ));
                    $currentLineWidth += $textWidth;
                    $pendingSpace = false;
                    continue;
                }

                if ($currentLine !== []) {
                    $lines[] = ['segments' => $currentLine, 'justify' => true];
                    $currentLine = [];
                    $currentLineWidth = 0.0;
                    $pendingSpace = false;
                    $text = $wordRun->text;
                }

                $chunks = $this->breakWordToFit($text, $wordFont, $size, $maxWidth);

                foreach ($chunks as $index => $chunk) {
                    if ($index === count($chunks) - 1) {
                        $currentLine = [new TextSegment(
                            $chunk,
                            $wordRun->color,
                            $wordRun->opacity,
                            $wordRun->link,
                            $wordRun->bold,
                            $wordRun->italic,
                            $wordRun->underline,
                            $wordRun->strikethrough,
                        )];
                        $currentLineWidth = $wordFont->measureTextWidth($chunk, $size);
                        continue;
                    }

                    $lines[] = ['segments' => [new TextSegment(
                        $chunk,
                        $wordRun->color,
                        $wordRun->opacity,
                        $wordRun->link,
                        $wordRun->bold,
                        $wordRun->italic,
                        $wordRun->underline,
                        $wordRun->strikethrough,
                    )], 'justify' => true];
                }
            }
        }

        if ($currentLine !== []) {
            $lines[] = ['segments' => $currentLine, 'justify' => false];
        }

        return $lines === [] ? [['segments' => [], 'justify' => false]] : $lines;
    }

    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $lines
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    private function applyOverflowToLines(
        array $lines,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?int $maxLines,
        TextOverflow $overflow,
    ): array {
        if ($maxLines === null || count($lines) <= $maxLines) {
            return $lines;
        }

        $visibleLines = array_slice($lines, 0, $maxLines);

        if ($overflow === TextOverflow::CLIP || $visibleLines === []) {
            return array_map(
                static fn (array $line): array => ['segments' => $line['segments'], 'justify' => false],
                $visibleLines,
            );
        }

        $lastIndex = array_key_last($visibleLines);
        $visibleLines[$lastIndex] = [
            'segments' => $this->appendEllipsisToLine($visibleLines[$lastIndex]['segments'], $baseFont, $size, $maxWidth),
            'justify' => false,
        ];

        return $visibleLines;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function calculateAlignedOffset(
        array $line,
        string $baseFont,
        int $size,
        float $maxWidth,
        TextAlign $align,
        bool $canJustify,
    ): float
    {
        if ($align === TextAlign::LEFT || $align === TextAlign::JUSTIFY) {
            return 0.0;
        }

        $lineWidth = 0.0;

        foreach ($line as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $lineWidth += $segmentFont->measureTextWidth($segment->text, $size);
        }

        $remainingWidth = max(0.0, $maxWidth - $lineWidth);

        if ($align === TextAlign::CENTER) {
            return $remainingWidth / 2;
        }

        return $remainingWidth;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function calculateJustifiedWordSpacing(
        array $line,
        string $baseFont,
        int $size,
        float $maxWidth,
        TextAlign $align,
        bool $canJustify,
    ): float {
        if ($align !== TextAlign::JUSTIFY || !$canJustify) {
            return 0.0;
        }

        $lineWidth = 0.0;
        $spaceCount = 0;
        $pieces = $this->splitSegmentsIntoWordPieces($line);

        foreach ($line as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $lineWidth += $segmentFont->measureTextWidth($segment->text, $size);
        }

        foreach ($pieces as $index => $piece) {
            if ($index === 0) {
                continue;
            }

            $spaceCount += $piece['leadingSpaces'];
        }

        if ($spaceCount <= 0) {
            return 0.0;
        }

        return max(0.0, $maxWidth - $lineWidth) / $spaceCount;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function renderJustifiedLine(
        self $page,
        array $line,
        float $x,
        float $y,
        string $baseFont,
        int $size,
        ?string $tag,
        float $maxWidth,
    ): void {
        $pieces = $this->splitSegmentsIntoWordPieces($line);
        $extraWordSpacing = $this->calculateJustifiedWordSpacing($line, $baseFont, $size, $maxWidth, TextAlign::JUSTIFY, true);
        $cursorX = $x;
        $isFirstWord = true;

        foreach ($pieces as $piece) {
            $segment = $piece['segment'];
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);

            if (!$isFirstWord) {
                $spaceWidth = $segmentFont->measureTextWidth(str_repeat(' ', $piece['leadingSpaces']), $size);
                $cursorX += $spaceWidth + ($extraWordSpacing * $piece['leadingSpaces']);
            }

            $page->addText(
                $segment->text,
                $cursorX,
                $y,
                $segmentFontName,
                $size,
                $tag,
                $segment->color,
                $segment->opacity,
                $segment->underline,
                $segment->strikethrough,
                $segment->link,
            );

            $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
            $isFirstWord = false;
        }
    }

    /**
     * @param array<int, TextSegment> $line
     * @return array<int, TextSegment>
     */
    private function appendEllipsisToLine(array $line, string $baseFont, int $size, float $maxWidth): array
    {
        $line = $this->trimTrailingWhitespaceFromLine($line);
        $ellipsisSegment = $this->buildEllipsisSegment($line);

        while ($line !== [] && $this->measureLineWidthWithSegment($line, $ellipsisSegment, $baseFont, $size) > $maxWidth) {
            $this->removeLastCharacterFromLine($line);
            $line = $this->trimTrailingWhitespaceFromLine($line);
            $ellipsisSegment = $this->buildEllipsisSegment($line);
        }

        while ($ellipsisSegment->text !== '' && $this->measureSegmentsWidth([$ellipsisSegment], $baseFont, $size) > $maxWidth) {
            $ellipsisSegment = new TextSegment(
                substr($ellipsisSegment->text, 0, -1),
                $ellipsisSegment->color,
                $ellipsisSegment->opacity,
                $ellipsisSegment->link,
                $ellipsisSegment->bold,
                $ellipsisSegment->italic,
                $ellipsisSegment->underline,
                $ellipsisSegment->strikethrough,
            );
        }

        if ($ellipsisSegment->text === '') {
            return $line;
        }

        $this->appendRun($line, $ellipsisSegment);

        return $line;
    }

    /**
     * @param array<int, TextSegment> $line
     * @return array<int, TextSegment>
     */
    private function trimTrailingWhitespaceFromLine(array $line): array
    {
        while ($line !== []) {
            $lastIndex = array_key_last($line);
            $trimmed = rtrim($line[$lastIndex]->text, ' ');

            if ($trimmed === $line[$lastIndex]->text) {
                break;
            }

            if ($trimmed === '') {
                unset($line[$lastIndex]);
                $line = array_values($line);
                continue;
            }

            $line[$lastIndex] = new TextSegment(
                $trimmed,
                $line[$lastIndex]->color,
                $line[$lastIndex]->opacity,
                $line[$lastIndex]->link,
                $line[$lastIndex]->bold,
                $line[$lastIndex]->italic,
                $line[$lastIndex]->underline,
                $line[$lastIndex]->strikethrough,
            );
            break;
        }

        return array_values($line);
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function buildEllipsisSegment(array $line): TextSegment
    {
        $lastIndex = array_key_last($line);

        if ($lastIndex === null) {
            return new TextSegment('...');
        }

        $lastSegment = $line[$lastIndex];

        return new TextSegment(
            '...',
            $lastSegment->color,
            $lastSegment->opacity,
            $lastSegment->link,
            $lastSegment->bold,
            $lastSegment->italic,
            $lastSegment->underline,
            $lastSegment->strikethrough,
        );
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function removeLastCharacterFromLine(array &$line): void
    {
        while ($line !== []) {
            $lastIndex = array_key_last($line);
            $characters = preg_split('//u', $line[$lastIndex]->text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($characters === []) {
                unset($line[$lastIndex]);
                $line = array_values($line);
                continue;
            }

            array_pop($characters);
            $updatedText = implode('', $characters);

            if ($updatedText === '') {
                unset($line[$lastIndex]);
                $line = array_values($line);
                continue;
            }

            $line[$lastIndex] = new TextSegment(
                $updatedText,
                $line[$lastIndex]->color,
                $line[$lastIndex]->opacity,
                $line[$lastIndex]->link,
                $line[$lastIndex]->bold,
                $line[$lastIndex]->italic,
                $line[$lastIndex]->underline,
                $line[$lastIndex]->strikethrough,
            );
            return;
        }
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function measureLineWidthWithSegment(array $line, TextSegment $segment, string $baseFont, int $size): float
    {
        $segments = $line;
        $this->appendRun($segments, $segment);

        return $this->measureSegmentsWidth($segments, $baseFont, $size);
    }

    /**
     * @param array<int, TextSegment> $segments
     */
    private function measureSegmentsWidth(array $segments, string $baseFont, int $size): float
    {
        $width = 0.0;

        foreach ($segments as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $width += $segmentFont->measureTextWidth($segment->text, $size);
        }

        return $width;
    }

    /**
     * @param array<int, TextSegment> $segments
     * @return list<array{segment: TextSegment, leadingSpaces: int}>
     */
    private function splitSegmentsIntoWordPieces(array $segments): array
    {
        $pieces = [];

        foreach ($segments as $segment) {
            $leadingSpaces = 0;

            foreach (preg_split('/( +)/', $segment->text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if (trim($part) === '') {
                    $leadingSpaces += strlen($part);
                    continue;
                }

                $pieces[] = [
                    'segment' => new TextSegment(
                        $part,
                        $segment->color,
                        $segment->opacity,
                        $segment->link,
                        $segment->bold,
                        $segment->italic,
                        $segment->underline,
                        $segment->strikethrough,
                    ),
                    'leadingSpaces' => $leadingSpaces,
                ];

                $leadingSpaces = 0;
            }
        }

        return $pieces;
    }

    /**
     * @return list<array{type: 'word', run: TextSegment}|array{type: 'space'}|array{type: 'newline'}>
     */
    private function tokenizeRun(TextSegment $run): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $run->text);
        /** @var list<array{type: 'word', run: TextSegment}|array{type: 'space'}|array{type: 'newline'}> $tokens */
        $tokens = [];
        $buffer = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($character === "\n") {
                if ($buffer !== '') {
                    $tokens[] = ['type' => 'word', 'run' => new TextSegment(
                        $buffer,
                        $run->color,
                        $run->opacity,
                        $run->link,
                        $run->bold,
                        $run->italic,
                        $run->underline,
                        $run->strikethrough,
                    )];
                    $buffer = '';
                }

                $tokens[] = ['type' => 'newline'];
                continue;
            }

                if (preg_match('/\s/u', $character) === 1) {
                if ($buffer !== '') {
                    $tokens[] = ['type' => 'word', 'run' => new TextSegment(
                        $buffer,
                        $run->color,
                        $run->opacity,
                        $run->link,
                        $run->bold,
                        $run->italic,
                        $run->underline,
                        $run->strikethrough,
                    )];
                    $buffer = '';
                }

                $tokens[] = ['type' => 'space'];
                continue;
            }

            $buffer .= $character;
        }

        if ($buffer !== '') {
            $tokens[] = ['type' => 'word', 'run' => new TextSegment(
                $buffer,
                $run->color,
                $run->opacity,
                $run->link,
                $run->bold,
                $run->italic,
                $run->underline,
                $run->strikethrough,
            )];
        }

        return $tokens;
    }

    /**
     * @param array<int, TextSegment> $runs
     */
    private function appendRun(array &$runs, TextSegment $run): void
    {
        $lastIndex = array_key_last($runs);

        if ($lastIndex === null) {
            $runs[] = $run;
            return;
        }

        $lastRun = $runs[$lastIndex];

        if (
            $lastRun->color === $run->color
            && $lastRun->opacity === $run->opacity
            && $lastRun->link === $run->link
            && $lastRun->bold === $run->bold
            && $lastRun->italic === $run->italic
            && $lastRun->underline === $run->underline
            && $lastRun->strikethrough === $run->strikethrough
        ) {
            $runs[$lastIndex] = new TextSegment(
                $lastRun->text . $run->text,
                $lastRun->color,
                $lastRun->opacity,
                $lastRun->link,
                $lastRun->bold,
                $lastRun->italic,
                $lastRun->underline,
                $lastRun->strikethrough,
            );
            return;
        }

        $runs[] = $run;
    }

    private function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        if (!$segment->bold && !$segment->italic) {
            return $baseFont;
        }

        $standardVariant = StandardFontName::resolveVariant($baseFont, $segment->bold, $segment->italic);

        if ($standardVariant !== null) {
            $this->registerFontIfNeeded($standardVariant);

            return $standardVariant;
        }

        foreach ($this->buildVariantCandidates($baseFont, $segment->bold, $segment->italic) as $candidate) {
            if ($candidate === $baseFont) {
                continue;
            }

            if ($this->hasRegisteredFont($candidate) || FontRegistry::has($candidate, $this->document->getFontConfig())) {
                $this->registerFontIfNeeded($candidate);

                return $candidate;
            }
        }

        return $baseFont;
    }

    /**
     * @return list<string>
     */
    private function buildVariantCandidates(string $baseFont, bool $bold, bool $italic): array
    {
        if (!$bold && !$italic) {
            return [$baseFont];
        }

        if ($bold && $italic) {
            $suffix = ['BoldItalic', 'BoldOblique'];
        } elseif ($bold) {
            $suffix = ['Bold'];
        } else {
            $suffix = ['Italic', 'Oblique'];
        }

        $candidates = [];

        foreach ($suffix as $variantSuffix) {
            if (str_ends_with($baseFont, '-Regular')) {
                $candidates[] = substr($baseFont, 0, -strlen('-Regular')) . '-' . $variantSuffix;
                continue;
            }

            if (str_ends_with($baseFont, '-Roman')) {
                $candidates[] = substr($baseFont, 0, -strlen('-Roman')) . '-' . $variantSuffix;
                continue;
            }

            $candidates[] = $baseFont . '-' . $variantSuffix;
        }

        return array_values(array_unique($candidates));
    }

    private function hasRegisteredFont(string $baseFont): bool
    {
        foreach ($this->document->fonts as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                return true;
            }
        }

        return false;
    }

    private function registerFontIfNeeded(string $baseFont): void
    {
        if ($this->hasRegisteredFont($baseFont)) {
            return;
        }

        $this->document->addFont($baseFont);
    }
}
