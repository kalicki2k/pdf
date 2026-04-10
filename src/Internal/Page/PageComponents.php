<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Text\PageParagraphRenderer;
use Kalle\Pdf\Feature\Text\PageTextElementRenderer;
use Kalle\Pdf\Feature\Text\StructureTag;
use Kalle\Pdf\Feature\Text\TextBoxOptions;
use Kalle\Pdf\Feature\Text\TextOptions;
use Kalle\Pdf\Feature\Text\TextSegment;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Geometry\Rect;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Navigation\LinkTarget;
use Kalle\Pdf\Style\BadgeStyle;
use Kalle\Pdf\Style\CalloutStyle;
use Kalle\Pdf\Style\PanelStyle;

/**
 * @internal Coordinates high-level page components such as badges, panels and callouts.
 */
final class PageComponents
{
    private const float DEFAULT_LINE_HEIGHT_FACTOR = 1.2;

    public function __construct(
        private readonly PageLinks $pageLinks,
        private readonly PageGraphics $pageGraphics,
        private readonly PageFonts $pageFonts,
        private readonly PageTextElementRenderer $pageTextElementRenderer,
        private readonly PageParagraphRenderer $pageParagraphRenderer,
        private readonly bool $requiresTaggedPdf,
        private readonly bool $requiresTaggedLinkAnnotations,
    ) {
    }

    public static function forPage(
        PageLinks $pageLinks,
        PageGraphics $pageGraphics,
        PageFonts $pageFonts,
        PageTextElementRenderer $pageTextElementRenderer,
        PageParagraphRenderer $pageParagraphRenderer,
        bool $requiresTaggedPdf,
        bool $requiresTaggedLinkAnnotations,
    ): self {
        return new self(
            $pageLinks,
            $pageGraphics,
            $pageFonts,
            $pageTextElementRenderer,
            $pageParagraphRenderer,
            $requiresTaggedPdf,
            $requiresTaggedLinkAnnotations,
        );
    }

    public function addBadge(
        string $text,
        Position $position,
        string $baseFont = 'Helvetica',
        int $size = 11,
        ?BadgeStyle $style = null,
        ?LinkTarget $link = null,
    ): void {
        if ($text === '') {
            throw new InvalidArgumentException('Badge text must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Badge font size must be greater than zero.');
        }

        $style ??= new BadgeStyle(
            fillColor: Color::gray(0.9),
        );

        $textWidth = $this->pageFonts->measureTextWidth($text, $baseFont, $size);
        $badgeWidth = $textWidth + ($style->paddingHorizontal * 2);
        $badgeHeight = $size + ($style->paddingVertical * 2);

        $this->pageGraphics->renderDecorativeContent(function () use ($position, $badgeWidth, $badgeHeight, $style): void {
            if ($style->cornerRadius > 0) {
                $this->pageGraphics->addRoundedRectangle(
                    new Rect($position->x, $position->y, $badgeWidth, $badgeHeight),
                    $style->cornerRadius,
                    $style->borderWidth,
                    $style->borderColor,
                    $style->fillColor,
                    $style->opacity,
                );

                return;
            }

            $this->pageGraphics->addRectangle(
                new Rect($position->x, $position->y, $badgeWidth, $badgeHeight),
                $style->borderWidth,
                $style->borderColor,
                $style->fillColor,
                $style->opacity,
            );
        });

        $this->pageTextElementRenderer->render(
            $text,
            new Position(
                $position->x + $style->paddingHorizontal,
                $position->y + $style->paddingVertical + ($size * 0.2),
            ),
            $baseFont,
            $size,
            new TextOptions(
                structureTag: $this->resolveComponentTextStructureTag(),
                color: $style->textColor,
                opacity: $style->opacity,
                link: $link,
            ),
        );

    }

    /**
     * @param string|list<TextSegment> $body
     */
    public function addPanel(
        string | array $body,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $title = null,
        string $bodyFont = 'Helvetica',
        ?PanelStyle $style = null,
        ?string $titleFont = null,
        ?LinkTarget $link = null,
    ): void {
        if ($width <= 0) {
            throw new InvalidArgumentException('Panel width must be greater than zero.');
        }

        if ($height <= 0) {
            throw new InvalidArgumentException('Panel height must be greater than zero.');
        }

        if ($title === null && $body === '') {
            throw new InvalidArgumentException('Panel requires a title or body.');
        }

        $style ??= new PanelStyle(
            fillColor: Color::gray(0.96),
            borderColor: Color::gray(0.75),
        );
        $titleFont ??= $bodyFont;
        $bindLinkToText = $this->shouldBindHighLevelComponentLinkToText($link);

        $this->pageGraphics->renderDecorativeContent(function () use ($style, $x, $y, $width, $height): void {
            if ($style->cornerRadius > 0) {
                $this->pageGraphics->addRoundedRectangle(
                    new Rect($x, $y, $width, $height),
                    $style->cornerRadius,
                    $style->borderWidth,
                    $style->borderColor,
                    $style->fillColor,
                    $style->opacity,
                );

                return;
            }

            $this->pageGraphics->addRectangle(
                new Rect($x, $y, $width, $height),
                $style->borderWidth,
                $style->borderColor,
                $style->fillColor,
                $style->opacity,
            );
        });

        $contentWidth = $width - ($style->paddingHorizontal * 2);

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Panel content width must be greater than zero.');
        }

        $bodyTopOffset = $style->paddingVertical;

        if ($title !== null && $title !== '') {
            $this->pageTextElementRenderer->render(
                $title,
                new Position(
                    $x + $style->paddingHorizontal,
                    $y + $height - $style->paddingVertical - $style->titleSize,
                ),
                $titleFont,
                $style->titleSize,
                new TextOptions(
                    structureTag: $this->resolveComponentTextStructureTag(),
                    color: $style->titleColor,
                    opacity: $style->opacity,
                    link: $bindLinkToText ? $link : null,
                ),
            );
            $bodyTopOffset += $style->titleSize + $style->titleSpacing;
        }

        if ($body !== '' && $body !== []) {
            $bodyLineHeight = $style->bodySize * self::DEFAULT_LINE_HEIGHT_FACTOR;
            $availableBodyHeight = $height - $bodyTopOffset - $style->paddingVertical;
            $maxLines = (int) floor($availableBodyHeight / $bodyLineHeight);

            if ($maxLines < 1) {
                throw new InvalidArgumentException('Panel height is too small for its content.');
            }

            $this->pageParagraphRenderer->addTextBox(
                text: $this->bindLinkToTextContent($body, $link),
                box: new Rect(
                    $x + $style->paddingHorizontal,
                    $y + $style->paddingVertical,
                    $contentWidth,
                    $availableBodyHeight,
                ),
                fontName: $bodyFont,
                size: $style->bodySize,
                options: new TextBoxOptions(
                    structureTag: $this->resolveComponentTextStructureTag(),
                    lineHeight: $bodyLineHeight,
                    color: $style->bodyColor,
                    opacity: $style->opacity,
                    align: $style->bodyAlign,
                    maxLines: $maxLines,
                    overflow: TextOverflow::ELLIPSIS,
                ),
            );
        }

        if ($link !== null && !$bindLinkToText) {
            $this->pageLinks->addLinkTarget(new Rect($x, $y, $width, $height), $link, null, null);
        }

    }

    /**
     * @param string|list<TextSegment> $body
     */
    public function addCallout(
        string | array $body,
        float $x,
        float $y,
        float $width,
        float $height,
        float $pointerX,
        float $pointerY,
        ?string $title = null,
        string $bodyFont = 'Helvetica',
        ?CalloutStyle $style = null,
        ?string $titleFont = null,
        ?LinkTarget $link = null,
    ): void {
        $style ??= new CalloutStyle(
            panelStyle: new PanelStyle(
                fillColor: Color::gray(0.96),
                borderColor: Color::gray(0.75),
            ),
        );
        $panelStyle = $style->panelStyle ?? new PanelStyle(
            fillColor: Color::gray(0.96),
            borderColor: Color::gray(0.75),
        );

        $this->addPanel(
            $body,
            $x,
            $y,
            $width,
            $height,
            $title,
            $bodyFont,
            $panelStyle,
            $titleFont,
            $link,
        );

        $pointerStrokeWidth = $style->pointerStrokeWidth ?? $panelStyle->borderWidth;
        $pointerStrokeColor = $style->pointerStrokeColor ?? $panelStyle->borderColor;
        $pointerFillColor = $style->pointerFillColor ?? $panelStyle->fillColor;
        $pointerOpacity = $style->pointerOpacity ?? $panelStyle->opacity;
        $halfBaseWidth = $style->pointerBaseWidth / 2;

        if ($pointerY <= $y) {
            $baseCenterX = max($x + $halfBaseWidth, min($x + $width - $halfBaseWidth, $pointerX));
            $baseY = $y;
            $points = [
                [$baseCenterX - $halfBaseWidth, $baseY],
                [$baseCenterX + $halfBaseWidth, $baseY],
                [$pointerX, $pointerY],
            ];
        } elseif ($pointerY >= $y + $height) {
            $baseCenterX = max($x + $halfBaseWidth, min($x + $width - $halfBaseWidth, $pointerX));
            $baseY = $y + $height;
            $points = [
                [$baseCenterX - $halfBaseWidth, $baseY],
                [$pointerX, $pointerY],
                [$baseCenterX + $halfBaseWidth, $baseY],
            ];
        } elseif ($pointerX <= $x) {
            $baseCenterY = max($y + $halfBaseWidth, min($y + $height - $halfBaseWidth, $pointerY));
            $baseX = $x;
            $points = [
                [$baseX, $baseCenterY - $halfBaseWidth],
                [$baseX, $baseCenterY + $halfBaseWidth],
                [$pointerX, $pointerY],
            ];
        } else {
            $baseCenterY = max($y + $halfBaseWidth, min($y + $height - $halfBaseWidth, $pointerY));
            $baseX = $x + $width;
            $points = [
                [$baseX, $baseCenterY - $halfBaseWidth],
                [$pointerX, $pointerY],
                [$baseX, $baseCenterY + $halfBaseWidth],
            ];
        }

        $this->pageGraphics->renderDecorativeContent(function () use ($points, $pointerStrokeWidth, $pointerStrokeColor, $pointerFillColor, $pointerOpacity): void {
            $this->pageGraphics->addPolygon(
                $points,
                $pointerStrokeWidth,
                $pointerStrokeColor,
                $pointerFillColor,
                $pointerOpacity,
            );
        });

    }

    /**
     * @param string|list<TextSegment> $text
     * @return string|list<TextSegment>
     */
    private function bindLinkToTextContent(string | array $text, ?LinkTarget $link): string | array
    {
        if ($link === null || !$this->shouldBindHighLevelComponentLinkToText($link)) {
            return $text;
        }

        if (is_string($text)) {
            return [new TextSegment($text, link: $link)];
        }

        return array_map(
            static fn (TextSegment $segment): TextSegment => $segment->link !== null
                ? $segment
                : new TextSegment(
                    $segment->text,
                    $segment->color,
                    $segment->opacity,
                    $link,
                    $segment->bold,
                    $segment->italic,
                    $segment->underline,
                    $segment->strikethrough,
                ),
            $text,
        );
    }

    private function shouldBindHighLevelComponentLinkToText(?LinkTarget $link): bool
    {
        return $link !== null
            && $this->requiresTaggedLinkAnnotations;
    }

    private function resolveComponentTextStructureTag(): ?StructureTag
    {
        if (!$this->requiresTaggedPdf) {
            return null;
        }

        return StructureTag::Paragraph;
    }
}
