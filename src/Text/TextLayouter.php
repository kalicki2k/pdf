<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class TextLayouter
{
    public function layout(
        LayoutContext $context,
        string $text,
    ): TextLayoutResult {
        $x = $context->contentArea->left;
        $y = $context->cursor->y;

        $lines = $this->wrapLines(
            text: $text,
            maxWidth: $context->contentArea->right - $x,
        );

        $lineHeight = 14.0;
        $nextCursor = $context->cursor->movedDown($lineHeight * count($lines));

        return TextLayoutResult::make(
            x: $x,
            y: $y,
            lines: $lines,
            nextCursor: $nextCursor,
        );
    }

    /**
     * @return list<string>
     */
    private function wrapLines(string $text, float $maxWidth): array
    {
        return [$text];
    }
}