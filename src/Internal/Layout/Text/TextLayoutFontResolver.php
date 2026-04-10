<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text;

use Closure;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;

/**
 * @internal Resolves fonts needed by text layout without exposing page-level wiring.
 */
final class TextLayoutFontResolver
{
    /**
     * @param Closure(string): FontDefinition $resolveFont
     * @param Closure(string, TextSegment): string $resolveStyledBaseFont
     */
    private function __construct(
        private readonly Closure $resolveFont,
        private readonly Closure $resolveStyledBaseFont,
    ) {
    }

    public static function forPageFonts(PageFonts $pageFonts): self
    {
        return new self(
            fn (string $baseFont): FontDefinition => $pageFonts->resolveFont($baseFont),
            fn (string $baseFont, TextSegment $segment): string => $pageFonts->resolveStyledBaseFont($baseFont, $segment),
        );
    }

    /**
     * @param Closure(string): FontDefinition $resolveFont
     * @param Closure(string, TextSegment): string $resolveStyledBaseFont
     */
    public static function fromCallables(Closure $resolveFont, Closure $resolveStyledBaseFont): self
    {
        return new self($resolveFont, $resolveStyledBaseFont);
    }

    public function resolveFont(string $baseFont): FontDefinition
    {
        return ($this->resolveFont)($baseFont);
    }

    public function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        return ($this->resolveStyledBaseFont)($baseFont, $segment);
    }
}
