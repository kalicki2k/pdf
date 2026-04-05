<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Graphics\Color;

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
        if ($fillColor !== null) {
            $page->addRectangle($x, $y, $width, $height, null, null, $fillColor);
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
            $page->addRectangle(
                $x,
                $y,
                $width,
                $height,
                $topBorder->width,
                $topBorder->color,
                null,
                $topBorder->opacity,
            );

            return;
        }

        if ($topBorder !== null) {
            $page->addLine($x, $y + $height, $x + $width, $y + $height, $topBorder->width, $topBorder->color, $topBorder->opacity);
        }

        if ($rightBorder !== null) {
            $page->addLine($x + $width, $y, $x + $width, $y + $height, $rightBorder->width, $rightBorder->color, $rightBorder->opacity);
        }

        if ($bottomBorder !== null) {
            $page->addLine($x, $y, $x + $width, $y, $bottomBorder->width, $bottomBorder->color, $bottomBorder->opacity);
        }

        if ($leftBorder !== null) {
            $page->addLine($x, $y, $x, $y + $height, $leftBorder->width, $leftBorder->color, $leftBorder->opacity);
        }
    }
}
