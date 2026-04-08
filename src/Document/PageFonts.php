<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFont;

/**
 * @internal Coordinates font resolution and font-related page resources.
 */
final class PageFonts
{
    public function __construct(private readonly Page $page)
    {
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
        return $this->page->resources->addFont($font);
    }

    public function updateUnicodeFontWidths(FontDefinition $font): void
    {
        if (
            !$font instanceof UnicodeFont
            || $font->descendantFont->cidToGidMap === null
            || $font->descendantFont->fontDescriptor === null
        ) {
            return;
        }

        $fontParser = new OpenTypeFontParser($font->descendantFont->fontDescriptor->fontFile->data);
        $widths = [];

        foreach ($font->getCodePointMap() as $cid => $codePointHex) {
            $utf16 = hex2bin($codePointHex);
            /** @var string $utf16 */
            $character = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $widths[$cid] = $fontParser->getAdvanceWidthForGlyphId($glyphId);
        }

        $font->descendantFont->setWidths($widths);
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
