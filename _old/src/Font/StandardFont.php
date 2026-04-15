<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

enum StandardFont: string
{
    case COURIER = 'Courier';
    case COURIER_BOLD = 'Courier-Bold';
    case COURIER_BOLD_OBLIQUE = 'Courier-BoldOblique';
    case COURIER_OBLIQUE = 'Courier-Oblique';
    case HELVETICA = 'Helvetica';
    case HELVETICA_BOLD = 'Helvetica-Bold';
    case HELVETICA_BOLD_OBLIQUE = 'Helvetica-BoldOblique';
    case HELVETICA_OBLIQUE = 'Helvetica-Oblique';
    case SYMBOL = 'Symbol';
    case TIMES_BOLD = 'Times-Bold';
    case TIMES_BOLD_ITALIC = 'Times-BoldItalic';
    case TIMES_ITALIC = 'Times-Italic';
    case TIMES_ROMAN = 'Times-Roman';
    case ZAPF_DINGBATS = 'ZapfDingbats';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (self $font): string => $font->value,
            self::cases(),
        );
    }

    public static function isValid(string $fontName): bool
    {
        return self::tryFrom($fontName) !== null;
    }

    public static function resolveVariant(string $fontName, bool $bold, bool $italic): ?self
    {
        $font = self::tryFrom($fontName);

        if ($font === null) {
            return null;
        }

        if (!$bold && !$italic) {
            return $font;
        }

        return match ($font) {
            self::COURIER,
            self::COURIER_BOLD,
            self::COURIER_OBLIQUE,
            self::COURIER_BOLD_OBLIQUE => match (true) {
                $bold && $italic => self::COURIER_BOLD_OBLIQUE,
                $bold => self::COURIER_BOLD,
                default => self::COURIER_OBLIQUE,
            },
            self::HELVETICA,
            self::HELVETICA_BOLD,
            self::HELVETICA_OBLIQUE,
            self::HELVETICA_BOLD_OBLIQUE => match (true) {
                $bold && $italic => self::HELVETICA_BOLD_OBLIQUE,
                $bold => self::HELVETICA_BOLD,
                default => self::HELVETICA_OBLIQUE,
            },
            self::TIMES_ROMAN,
            self::TIMES_BOLD,
            self::TIMES_ITALIC,
            self::TIMES_BOLD_ITALIC => match (true) {
                $bold && $italic => self::TIMES_BOLD_ITALIC,
                $bold => self::TIMES_BOLD,
                default => self::TIMES_ITALIC,
            },
            default => null,
        };
    }
}
