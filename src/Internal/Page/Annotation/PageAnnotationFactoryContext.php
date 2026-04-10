<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Closure;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Page;

/**
 * @internal Provides object ids and font resources for page annotation building.
 */
final class PageAnnotationFactoryContext
{
    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(string): FontDefinition $resolveFont
     * @param Closure(FontDefinition): string $registerFontResource
     */
    private function __construct(
        private readonly Closure $nextObjectId,
        private readonly Closure $resolveFont,
        private readonly Closure $registerFontResource,
    ) {
    }

    public static function forPage(Page $page, PageFonts $pageFonts): self
    {
        return new self(
            fn (): int => $page->getDocument()->getUniqObjectId(),
            fn (string $baseFont): FontDefinition => $pageFonts->resolveFont($baseFont),
            fn (FontDefinition $font): string => $pageFonts->registerFontResource($font),
        );
    }

    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(string): FontDefinition $resolveFont
     * @param Closure(FontDefinition): string $registerFontResource
     */
    public static function fromCallables(
        Closure $nextObjectId,
        Closure $resolveFont,
        Closure $registerFontResource,
    ): self {
        return new self($nextObjectId, $resolveFont, $registerFontResource);
    }

    public function nextObjectId(): int
    {
        return ($this->nextObjectId)();
    }

    public function resolveFont(string $baseFont): FontDefinition
    {
        return ($this->resolveFont)($baseFont);
    }

    public function registerFontResource(FontDefinition $font): string
    {
        return ($this->registerFontResource)($font);
    }
}
