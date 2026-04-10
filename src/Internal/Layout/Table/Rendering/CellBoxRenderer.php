<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Page\Content\PageGraphics;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Layout\Position;
use Kalle\Pdf\Layout\Rect;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Table\Style\TableBorder;

final readonly class CellBoxRenderer
{
    public function __construct(
        private TableStyleResolver $styleResolver,
    ) {
    }

    public function render(
        Page $page,
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $fillColor,
        ?TableBorder $defaultBorder,
        ?TableBorder $rowBorder,
        ?TableBorder $cellBorder,
        bool $renderTopBorder = true,
        bool $renderRightBorder = true,
        bool $renderBottomBorder = true,
        bool $renderLeftBorder = true,
    ): void {
        $pageGraphics = PageGraphics::forPage($page);

        $pageGraphics->renderDecorativeContent(function () use (
            $pageGraphics,
            $x,
            $y,
            $width,
            $height,
            $fillColor,
            $defaultBorder,
            $rowBorder,
            $cellBorder,
            $renderTopBorder,
            $renderRightBorder,
            $renderBottomBorder,
            $renderLeftBorder,
        ): void {
            if ($fillColor !== null) {
                $pageGraphics->addRectangle(new Rect($x, $y, $width, $height), null, null, $fillColor);
            }

            $topBorder = $renderTopBorder ? $this->styleResolver->resolveBorderSide('top', $defaultBorder, $rowBorder, $cellBorder) : null;
            $rightBorder = $renderRightBorder ? $this->styleResolver->resolveBorderSide('right', $defaultBorder, $rowBorder, $cellBorder) : null;
            $bottomBorder = $renderBottomBorder ? $this->styleResolver->resolveBorderSide('bottom', $defaultBorder, $rowBorder, $cellBorder) : null;
            $leftBorder = $renderLeftBorder ? $this->styleResolver->resolveBorderSide('left', $defaultBorder, $rowBorder, $cellBorder) : null;

            if ($topBorder === null && $rightBorder === null && $bottomBorder === null && $leftBorder === null) {
                return;
            }

            if (
                $topBorder !== null
                && $rightBorder !== null
                && $bottomBorder !== null
                && $leftBorder !== null
                && $this->styleResolver->bordersAreEquivalent($topBorder, $rightBorder, $bottomBorder, $leftBorder)
            ) {
                $pageGraphics->addRectangle(
                    new Rect($x, $y, $width, $height),
                    $topBorder->width,
                    $topBorder->color,
                    null,
                    $topBorder->opacity,
                );

                return;
            }

            if ($topBorder !== null) {
                $pageGraphics->addLine(new Position($x, $y + $height), new Position($x + $width, $y + $height), $topBorder->width, $topBorder->color, $topBorder->opacity);
            }

            if ($rightBorder !== null) {
                $pageGraphics->addLine(new Position($x + $width, $y), new Position($x + $width, $y + $height), $rightBorder->width, $rightBorder->color, $rightBorder->opacity);
            }

            if ($bottomBorder !== null) {
                $pageGraphics->addLine(new Position($x, $y), new Position($x + $width, $y), $bottomBorder->width, $bottomBorder->color, $bottomBorder->opacity);
            }

            if ($leftBorder !== null) {
                $pageGraphics->addLine(new Position($x, $y), new Position($x, $y + $height), $leftBorder->width, $leftBorder->color, $leftBorder->opacity);
            }
        });
    }
}
