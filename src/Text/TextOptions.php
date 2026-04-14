<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Layout\PositionMode;
use Kalle\Pdf\Page\LinkTarget;

/**
 * Describes the layout, font and semantic defaults for a rendered text block.
 */
final readonly class TextOptions
{
    /**
     * Default font size for generic text blocks created via `make()`.
     */
    public const float DEFAULT_FONT_SIZE = 18.0;

    /**
     * Default font size for regular body copy presets.
     */
    public const float BODY_FONT_SIZE = 12.0;

    /**
     * Default font size for compact secondary text.
     */
    public const float SMALL_FONT_SIZE = 9.0;

    /**
     * Default font size for captions and supporting labels.
     */
    public const float CAPTION_FONT_SIZE = 10.0;

    /**
     * Default font size for headline-style text.
     */
    public const float HEADING_FONT_SIZE = 22.0;

    /**
     * Default line height for body copy presets.
     */
    public const float BODY_LINE_HEIGHT = 14.4;

    /**
     * Default line height for compact secondary text.
     */
    public const float SMALL_LINE_HEIGHT = 12.0;

    /**
     * Default line height for caption-style text.
     */
    public const float CAPTION_LINE_HEIGHT = 13.0;

    /**
     * Default line height for headline-style text.
     */
    public const float HEADING_LINE_HEIGHT = 28.0;

    /**
     * Standard PDF font used when no embedded font is configured.
     */
    public const string DEFAULT_FONT_NAME = StandardFont::HELVETICA->value;

    /**
     * Creates a generic text option object for block rendering.
     *
     * @param ?float $left Absolute left text position. When omitted, the current flow cursor or page margin is used.
     * @param ?float $right Absolute right inset used to resolve the text block width or anchor.
     * @param ?float $top Absolute top text anchor for the first rendered line. The builder resolves this to the first baseline.
     * @param ?float $bottom Absolute baseline anchor for the first rendered line.
     * @param ?PositionMode $positionMode Reference area for explicit insets. `ABSOLUTE` uses the full page, `RELATIVE` uses the content area.
     * @param ?float $width Fixed layout width used for wrapping and alignment.
     * @param ?float $maxWidth Optional maximum width when no fixed width is configured.
     * @param float $fontSize Font size in PDF points.
     * @param ?float $lineHeight Explicit line height in PDF points. Defaults to `fontSize * 1.2`.
     * @param ?float $spacingBefore Additional vertical spacing applied before the block in flow layout.
     * @param ?float $spacingAfter Additional vertical spacing applied after the block in flow layout.
     * @param string $fontName Standard PDF font name used when no embedded font is configured.
     * @param ?EmbeddedFontSource $embeddedFont Embedded font source used instead of a standard PDF font.
     * @param ?StandardFontEncoding $fontEncoding Explicit encoding for standard PDF fonts.
     * @param ?Color $color Fill color for the rendered text.
     * @param bool $kerning Whether kerning adjustments should be applied when supported by the active font.
     * @param TextDirection $baseDirection Base text direction for shaping and bidi resolution.
     * @param TextAlign $align Horizontal block alignment inside the available width.
     * @param float $firstLineIndent Additional indent applied only to the first line of a wrapped block.
     * @param float $hangingIndent Additional indent applied to continuation lines after the first line.
     * @param LinkTarget|TextLink|null $link Optional link metadata for the entire text block.
     * @param ?TaggedStructureTag $tag Optional tagged-PDF leaf role for the rendered text block.
     * @param TextSemantic $semantic Whether the text should be treated as logical content or as an artifact.
     */
    public static function make(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?PositionMode $positionMode = null,
        ?float $width = null,
        ?float $maxWidth = null,
        float $fontSize = self::DEFAULT_FONT_SIZE,
        ?float $lineHeight = null,
        ?float $spacingBefore = null,
        ?float $spacingAfter = null,
        string $fontName = self::DEFAULT_FONT_NAME,
        ?EmbeddedFontSource $embeddedFont = null,
        ?StandardFontEncoding $fontEncoding = null,
        ?Color $color = null,
        bool $kerning = true,
        TextDirection $baseDirection = TextDirection::LTR,
        TextAlign $align = TextAlign::LEFT,
        float $firstLineIndent = 0.0,
        float $hangingIndent = 0.0,
        LinkTarget | TextLink | null $link = null,
        ?TaggedStructureTag $tag = null,
        TextSemantic $semantic = TextSemantic::CONTENT,
    ): self {
        return new self(
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            positionMode: $positionMode,
            width: $width,
            maxWidth: $maxWidth,
            fontSize: $fontSize,
            lineHeight: $lineHeight,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            fontName: $fontName,
            embeddedFont: $embeddedFont,
            fontEncoding: $fontEncoding,
            color: $color,
            kerning: $kerning,
            baseDirection: $baseDirection,
            align: $align,
            firstLineIndent: $firstLineIndent,
            hangingIndent: $hangingIndent,
            link: $link,
            tag: $tag,
            semantic: $semantic,
        );
    }

    /**
     * Creates body-copy defaults for regular running text.
     *
     * Accepts the same overrides as `make()`, but starts from the body preset size and line height.
     */
    public static function body(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?PositionMode $positionMode = null,
        ?float $width = null,
        ?float $maxWidth = null,
        float $fontSize = self::BODY_FONT_SIZE,
        ?float $lineHeight = self::BODY_LINE_HEIGHT,
        ?float $spacingBefore = null,
        ?float $spacingAfter = null,
        string $fontName = self::DEFAULT_FONT_NAME,
        ?EmbeddedFontSource $embeddedFont = null,
        ?StandardFontEncoding $fontEncoding = null,
        ?Color $color = null,
        bool $kerning = true,
        TextDirection $baseDirection = TextDirection::LTR,
        TextAlign $align = TextAlign::LEFT,
        float $firstLineIndent = 0.0,
        float $hangingIndent = 0.0,
        LinkTarget | TextLink | null $link = null,
        ?TaggedStructureTag $tag = null,
        TextSemantic $semantic = TextSemantic::CONTENT,
    ): self {
        return self::make(
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            positionMode: $positionMode,
            width: $width,
            maxWidth: $maxWidth,
            fontSize: $fontSize,
            lineHeight: $lineHeight,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            fontName: $fontName,
            embeddedFont: $embeddedFont,
            fontEncoding: $fontEncoding,
            color: $color,
            kerning: $kerning,
            baseDirection: $baseDirection,
            align: $align,
            firstLineIndent: $firstLineIndent,
            hangingIndent: $hangingIndent,
            link: $link,
            tag: $tag,
            semantic: $semantic,
        );
    }

    /**
     * Creates a compact text preset for secondary metadata, labels and dense tables.
     *
     * Accepts the same overrides as `make()`, but starts from the small preset size and line height.
     */
    public static function small(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?PositionMode $positionMode = null,
        ?float $width = null,
        ?float $maxWidth = null,
        float $fontSize = self::SMALL_FONT_SIZE,
        ?float $lineHeight = self::SMALL_LINE_HEIGHT,
        ?float $spacingBefore = null,
        ?float $spacingAfter = null,
        string $fontName = self::DEFAULT_FONT_NAME,
        ?EmbeddedFontSource $embeddedFont = null,
        ?StandardFontEncoding $fontEncoding = null,
        ?Color $color = null,
        bool $kerning = true,
        TextDirection $baseDirection = TextDirection::LTR,
        TextAlign $align = TextAlign::LEFT,
        float $firstLineIndent = 0.0,
        float $hangingIndent = 0.0,
        LinkTarget | TextLink | null $link = null,
        ?TaggedStructureTag $tag = null,
        TextSemantic $semantic = TextSemantic::CONTENT,
    ): self {
        return self::make(
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            positionMode: $positionMode,
            width: $width,
            maxWidth: $maxWidth,
            fontSize: $fontSize,
            lineHeight: $lineHeight,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            fontName: $fontName,
            embeddedFont: $embeddedFont,
            fontEncoding: $fontEncoding,
            color: $color,
            kerning: $kerning,
            baseDirection: $baseDirection,
            align: $align,
            firstLineIndent: $firstLineIndent,
            hangingIndent: $hangingIndent,
            link: $link,
            tag: $tag,
            semantic: $semantic,
        );
    }

    /**
     * Creates a caption preset for supporting text, notes and annotations.
     *
     * Accepts the same overrides as `make()`, but starts from the caption preset size and line height.
     */
    public static function caption(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?PositionMode $positionMode = null,
        ?float $width = null,
        ?float $maxWidth = null,
        float $fontSize = self::CAPTION_FONT_SIZE,
        ?float $lineHeight = self::CAPTION_LINE_HEIGHT,
        ?float $spacingBefore = null,
        ?float $spacingAfter = null,
        string $fontName = self::DEFAULT_FONT_NAME,
        ?EmbeddedFontSource $embeddedFont = null,
        ?StandardFontEncoding $fontEncoding = null,
        ?Color $color = null,
        bool $kerning = true,
        TextDirection $baseDirection = TextDirection::LTR,
        TextAlign $align = TextAlign::LEFT,
        float $firstLineIndent = 0.0,
        float $hangingIndent = 0.0,
        LinkTarget | TextLink | null $link = null,
        ?TaggedStructureTag $tag = null,
        TextSemantic $semantic = TextSemantic::CONTENT,
    ): self {
        return self::make(
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            positionMode: $positionMode,
            width: $width,
            maxWidth: $maxWidth,
            fontSize: $fontSize,
            lineHeight: $lineHeight,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            fontName: $fontName,
            embeddedFont: $embeddedFont,
            fontEncoding: $fontEncoding,
            color: $color,
            kerning: $kerning,
            baseDirection: $baseDirection,
            align: $align,
            firstLineIndent: $firstLineIndent,
            hangingIndent: $hangingIndent,
            link: $link,
            tag: $tag,
            semantic: $semantic,
        );
    }

    /**
     * Creates a headline preset for prominent titles and section headings.
     *
     * Accepts the same overrides as `make()`, but starts from the heading preset size and line height.
     */
    public static function heading(
        ?float $left = null,
        ?float $right = null,
        ?float $top = null,
        ?float $bottom = null,
        ?PositionMode $positionMode = null,
        ?float $width = null,
        ?float $maxWidth = null,
        float $fontSize = self::HEADING_FONT_SIZE,
        ?float $lineHeight = self::HEADING_LINE_HEIGHT,
        ?float $spacingBefore = null,
        ?float $spacingAfter = null,
        string $fontName = self::DEFAULT_FONT_NAME,
        ?EmbeddedFontSource $embeddedFont = null,
        ?StandardFontEncoding $fontEncoding = null,
        ?Color $color = null,
        bool $kerning = true,
        TextDirection $baseDirection = TextDirection::LTR,
        TextAlign $align = TextAlign::LEFT,
        float $firstLineIndent = 0.0,
        float $hangingIndent = 0.0,
        LinkTarget | TextLink | null $link = null,
        ?TaggedStructureTag $tag = null,
        TextSemantic $semantic = TextSemantic::CONTENT,
    ): self {
        return self::make(
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
            positionMode: $positionMode,
            width: $width,
            maxWidth: $maxWidth,
            fontSize: $fontSize,
            lineHeight: $lineHeight,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
            fontName: $fontName,
            embeddedFont: $embeddedFont,
            fontEncoding: $fontEncoding,
            color: $color,
            kerning: $kerning,
            baseDirection: $baseDirection,
            align: $align,
            firstLineIndent: $firstLineIndent,
            hangingIndent: $hangingIndent,
            link: $link,
            tag: $tag,
            semantic: $semantic,
        );
    }

    /**
     * @param ?float $left Absolute left text position. When omitted, the current flow cursor or page margin is used.
     * @param ?float $right Absolute right inset used to resolve the text block width or anchor.
     * @param ?float $top Absolute top text anchor for the first rendered line. The builder resolves this to the first baseline.
     * @param ?float $bottom Absolute baseline anchor for the first rendered line.
     * @param ?PositionMode $positionMode Reference area for explicit insets. `ABSOLUTE` uses the full page, `RELATIVE` uses the content area.
     * @param ?float $width Fixed layout width used for wrapping and alignment.
     * @param ?float $maxWidth Optional maximum width when no fixed width is configured.
     * @param float $fontSize Font size in PDF points.
     * @param ?float $lineHeight Explicit line height in PDF points. Defaults to `fontSize * 1.2`.
     * @param ?float $spacingBefore Additional vertical spacing applied before the block in flow layout.
     * @param ?float $spacingAfter Additional vertical spacing applied after the block in flow layout.
     * @param string $fontName Standard PDF font name used when no embedded font is configured.
     * @param ?EmbeddedFontSource $embeddedFont Embedded font source used instead of a standard PDF font.
     * @param ?StandardFontEncoding $fontEncoding Explicit encoding for standard PDF fonts.
     * @param ?Color $color Fill color for the rendered text.
     * @param bool $kerning Whether kerning adjustments should be applied when supported by the active font.
     * @param TextDirection $baseDirection Base text direction for shaping and bidi resolution.
     * @param TextAlign $align Horizontal block alignment inside the available width.
     * @param float $firstLineIndent Additional indent applied only to the first line of a wrapped block.
     * @param float $hangingIndent Additional indent applied to continuation lines after the first line.
     * @param LinkTarget|TextLink|null $link Optional link metadata for the entire text block.
     * @param ?TaggedStructureTag $tag Optional tagged-PDF leaf role for the rendered text block.
     * @param TextSemantic $semantic Whether the text should be treated as logical content or as an artifact.
     */
    private function __construct(
        public ?float $left = null,
        public ?float $right = null,
        public ?float $top = null,
        public ?float $bottom = null,
        public ?PositionMode $positionMode = null,
        public ?float $width = null,
        public ?float $maxWidth = null,
        public float $fontSize = self::DEFAULT_FONT_SIZE,
        public ?float $lineHeight = null,
        public ?float $spacingBefore = null,
        public ?float $spacingAfter = null,
        public string $fontName = self::DEFAULT_FONT_NAME,
        public ?EmbeddedFontSource $embeddedFont = null,
        public ?StandardFontEncoding $fontEncoding = null,
        public ?Color $color = null,
        public bool $kerning = true,
        public TextDirection $baseDirection = TextDirection::LTR,
        public TextAlign $align = TextAlign::LEFT,
        public float $firstLineIndent = 0.0,
        public float $hangingIndent = 0.0,
        public LinkTarget | TextLink | null $link = null,
        public ?TaggedStructureTag $tag = null,
        public TextSemantic $semantic = TextSemantic::CONTENT,
    ) {
        if ($this->top !== null && $this->bottom !== null) {
            throw new InvalidArgumentException('TextOptions top and bottom cannot be combined.');
        }

        if ($this->positionMode === PositionMode::STATIC) {
            throw new InvalidArgumentException('TextOptions do not support static position mode. Omit insets to use flow layout.');
        }
    }
}
