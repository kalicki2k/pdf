<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Object\IndirectObject;

/**
 * @internal Manages document font registrations and lookup.
 */
final class DocumentFontRegistry
{
    /**
     * @param array<int, FontDefinition&IndirectObject> $fonts
     */
    public function __construct(
        private array &$fonts,
        private readonly DocumentFontFactory $fontFactory,
        private readonly DocumentProfileGuard $profileGuard,
    ) {
    }

    public function registerFont(
        string $fontName,
        string $subtype = 'Type1',
        ?string $encoding = null,
        bool $unicode = false,
        ?string $fontFilePath = null,
    ): void {
        $options = $this->fontFactory->resolveRegistrationOptions(
            $fontName,
            $subtype,
            $encoding,
            $unicode,
            $fontFilePath,
        );
        $this->profileGuard->assertAllowsFontRegistration($options);
        $this->fonts = [
            ...$this->fonts,
            $this->fontFactory->createFont($options),
        ];
    }

    public function getFontByBaseFont(string $baseFont): ?FontDefinition
    {
        return array_find(
            $this->fonts,
            static fn (FontDefinition $font): bool => $font->getBaseFont() === $baseFont,
        );
    }

    /**
     * @return array<int, FontDefinition&IndirectObject>
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }
}
