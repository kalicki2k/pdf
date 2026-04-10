<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Resources;

use InvalidArgumentException;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Text\TextSegment;

/**
 * @internal Coordinates font resolution and font-related page resources.
 */
final class PageFonts
{
    public static function forPage(Page $page): self
    {
        return new self(
            $page,
            new UnicodeFontWidthUpdater(),
        );
    }

    public function __construct(
        private readonly Page $page,
        private readonly UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
    ) {
    }

    public function resolveFont(string $baseFont): FontDefinition
    {
        foreach ($this->page->getDocument()->getFonts() as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                return $registeredFont;
            }
        }

        if ($this->page->getDocument()->getProfile()->requiresEmbeddedUnicodeFonts()) {
            throw new InvalidArgumentException(sprintf(
                "Profile %s requires embedded Unicode fonts in the current implementation. Font '%s' is not registered.",
                $this->page->getDocument()->getProfile()->name(),
                $baseFont,
            ));
        }

        throw new InvalidArgumentException("Font '$baseFont' is not registered.");
    }

    public function registerFontResource(FontDefinition $font): string
    {
        return $this->page->addFontResource($font);
    }

    public function updateUnicodeFontWidths(FontDefinition $font): void
    {
        $this->unicodeFontWidthUpdater->update($font);
    }

    public function measureTextWidth(string $text, string $baseFont, int $size): float
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Text size must be greater than zero.');
        }

        return $this->resolveFont($baseFont)->measureTextWidth($text, $size);
    }

    public function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
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
            if ($this->hasRegisteredFont($candidate) || FontRegistry::has($candidate, $this->page->getDocument()->getFontConfig())) {
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
        return array_any(
            $this->page->getDocument()->getFonts(),
            static fn (FontDefinition $registeredFont): bool => $registeredFont->getBaseFont() === $baseFont,
        );
    }

    private function registerFontIfNeeded(string $baseFont): void
    {
        if ($this->hasRegisteredFont($baseFont)) {
            return;
        }

        $this->page->getDocument()->registerFont($baseFont);
    }
}
