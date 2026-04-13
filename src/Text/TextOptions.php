<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Page\LinkTarget;

/**
 * Describes the layout, font and semantic defaults for a rendered text block.
 */
final readonly class TextOptions
{
    /**
     * @param ?float $x Absolute left text position. When omitted, the current flow cursor or page margin is used.
     * @param ?float $y Absolute top-baseline anchor for the first rendered line. When omitted, normal text flow placement is used.
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
    public function __construct(
        public ?float $x = null,
        public ?float $y = null,
        public ?float $width = null,
        public ?float $maxWidth = null,
        public float $fontSize = 18.0,
        public ?float $lineHeight = null,
        public ?float $spacingBefore = null,
        public ?float $spacingAfter = null,
        public string $fontName = StandardFont::HELVETICA->value,
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
    }
}
