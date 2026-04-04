<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final class Units
{
    private const MM_TO_POINTS = 72.0 / 25.4;

    private function __construct()
    {
    }

    public static function mm(float $value): float
    {
        return $value * self::MM_TO_POINTS;
    }

    public static function pt(float $value): float
    {
        return $value;
    }

    public static function cm(float $value): float
    {
        return self::mm($value * 10.0);
    }

    public static function inch(float $value): float
    {
        return $value * 72.0;
    }
}
