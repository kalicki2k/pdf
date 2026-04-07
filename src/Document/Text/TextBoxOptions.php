<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use Kalle\Pdf\Document\Geometry\Insets;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Structure\StructElem;

/**
 * Controls how text is rendered inside a fixed rectangle.
 *
 * The text box does not draw a background or border.
 * It only controls text layout and text style inside the given width and height.
 */
final readonly class TextBoxOptions
{
    /**
     * @param StructureTag|null $structureTag Optional structure tag for tagged PDF output.
     * @param float|null $lineHeight Space between text lines. If `null`, a default value is calculated from the font size.
     * @param Color|null $color Default text color for the whole text box, unless a `TextSegment` uses its own color.
     * @param Opacity|null $opacity Optional opacity for the whole text box content.
     * @param HorizontalAlign $align Horizontal text alignment inside the usable box width.
     * @param VerticalAlign $verticalAlign Vertical alignment of the full text block inside the box height.
     * @param int|null $maxLines Maximum number of visible lines. If `null`, the box height decides the limit.
     * @param TextOverflow $overflow Defines if extra text is cut or shortened with an ellipsis.
     * @param Insets $padding Inner space between the box edges and the text area.
     */
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?float $lineHeight = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public HorizontalAlign $align = HorizontalAlign::LEFT,
        public VerticalAlign $verticalAlign = VerticalAlign::TOP,
        public ?int $maxLines = null,
        public TextOverflow $overflow = TextOverflow::CLIP,
        public Insets $padding = new Insets(),
    ) {
    }
}
