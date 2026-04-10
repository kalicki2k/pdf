<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

final class StandardFontName
{
    public const COURIER = 'Courier';
    public const COURIER_BOLD = 'Courier-Bold';
    public const COURIER_BOLD_OBLIQUE = 'Courier-BoldOblique';
    public const COURIER_OBLIQUE = 'Courier-Oblique';
    public const HELVETICA = 'Helvetica';
    public const HELVETICA_BOLD = 'Helvetica-Bold';
    public const HELVETICA_BOLD_OBLIQUE = 'Helvetica-BoldOblique';
    public const HELVETICA_OBLIQUE = 'Helvetica-Oblique';
    public const SYMBOL = 'Symbol';
    public const TIMES_BOLD = 'Times-Bold';
    public const TIMES_BOLD_ITALIC = 'Times-BoldItalic';
    public const TIMES_ITALIC = 'Times-Italic';
    public const TIMES_ROMAN = 'Times-Roman';
    public const ZAPF_DINGBATS = 'ZapfDingbats';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::COURIER,
            self::COURIER_BOLD,
            self::COURIER_BOLD_OBLIQUE,
            self::COURIER_OBLIQUE,
            self::HELVETICA,
            self::HELVETICA_BOLD,
            self::HELVETICA_BOLD_OBLIQUE,
            self::HELVETICA_OBLIQUE,
            self::SYMBOL,
            self::TIMES_BOLD,
            self::TIMES_BOLD_ITALIC,
            self::TIMES_ITALIC,
            self::TIMES_ROMAN,
            self::ZAPF_DINGBATS,
        ];
    }

    public static function isValid(string $fontName): bool
    {
        return in_array($fontName, self::all(), true);
    }

    public static function resolveVariant(string $fontName, bool $bold, bool $italic): ?string
    {
        if (!$bold && !$italic) {
            return $fontName;
        }

        return match ($fontName) {
            self::COURIER, self::COURIER_BOLD, self::COURIER_OBLIQUE, self::COURIER_BOLD_OBLIQUE => match (true) {
                $bold && $italic => self::COURIER_BOLD_OBLIQUE,
                $bold => self::COURIER_BOLD,
                default => self::COURIER_OBLIQUE,
            },
            self::HELVETICA, self::HELVETICA_BOLD, self::HELVETICA_OBLIQUE, self::HELVETICA_BOLD_OBLIQUE => match (true) {
                $bold && $italic => self::HELVETICA_BOLD_OBLIQUE,
                $bold => self::HELVETICA_BOLD,
                default => self::HELVETICA_OBLIQUE,
            },
            self::TIMES_ROMAN, self::TIMES_BOLD, self::TIMES_ITALIC, self::TIMES_BOLD_ITALIC => match (true) {
                $bold && $italic => self::TIMES_BOLD_ITALIC,
                $bold => self::TIMES_BOLD,
                default => self::TIMES_ITALIC,
            },
            default => null,
        };
    }
}
