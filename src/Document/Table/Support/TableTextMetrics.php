<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Support;

final class TableTextMetrics
{
    public function resolveAlignmentHeight(int $lineCount, int $fontSize, float $lineHeight): float
    {
        if ($lineCount <= 0) {
            return 0.0;
        }

        $alignmentHeight = $fontSize + (max(0, $lineCount - 1) * $lineHeight);

        if ($lineCount === 1) {
            $alignmentHeight += $this->resolveBottomTextInset($fontSize, $lineHeight);
        }

        return $alignmentHeight;
    }

    public function resolveContentHeight(int $lineCount, int $fontSize, float $lineHeight): float
    {
        if ($lineCount <= 0) {
            return 0.0;
        }

        $contentHeight = $fontSize + (max(0, $lineCount - 1) * $lineHeight);

        if ($lineCount > 1) {
            $contentHeight += $this->resolveBottomTextInset($fontSize, $lineHeight);
        }

        return $contentHeight;
    }

    public function resolveFittingLineCount(float $availableTextHeight, float $lineHeight, int $fontSize): int
    {
        if ($availableTextHeight <= 0) {
            return 0;
        }

        if ($availableTextHeight <= $fontSize) {
            return 1;
        }

        $bottomTextInset = $this->resolveBottomTextInset($fontSize, $lineHeight);
        $heightAfterFirstLine = $availableTextHeight - $fontSize - $bottomTextInset;

        if ($heightAfterFirstLine <= 0) {
            return 1;
        }

        return max(1, 1 + (int) floor(($heightAfterFirstLine + 0.001) / $lineHeight));
    }

    private function resolveBottomTextInset(int $fontSize, float $lineHeight): float
    {
        return max(0.0, $lineHeight - $fontSize);
    }
}
