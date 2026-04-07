<?php

declare(strict_types=1);

namespace Kalle\Pdf;

final class PdfVersion
{
    public const float V1_0 = 1.0;
    public const float V1_1 = 1.1;
    public const float V1_2 = 1.2;
    public const float V1_3 = 1.3;
    public const float V1_4 = 1.4;
    public const float V1_5 = 1.5;
    public const float V1_6 = 1.6;
    public const float V1_7 = 1.7;
    public const float V2_0 = 2.0;

    /**
     * @return list<float>
     */
    public static function all(): array
    {
        return [
            self::V1_0,
            self::V1_1,
            self::V1_2,
            self::V1_3,
            self::V1_4,
            self::V1_5,
            self::V1_6,
            self::V1_7,
            self::V2_0,
        ];
    }

    public static function format(float $version): string
    {
        return number_format($version, 1, '.', '');
    }
}
