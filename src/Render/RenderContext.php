<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;

final class RenderContext
{
    private static ?StandardObjectEncryptor $objectEncryptor = null;
    private static ?int $currentObjectId = null;

    public static function setObjectEncryptor(?StandardObjectEncryptor $objectEncryptor): void
    {
        self::$objectEncryptor = $objectEncryptor;
    }

    public static function enterObject(int $objectId): void
    {
        self::$currentObjectId = $objectId;
    }

    public static function leaveObject(): void
    {
        self::$currentObjectId = null;
    }

    public static function encryptString(string $value): ?string
    {
        if (self::$objectEncryptor === null || self::$currentObjectId === null) {
            return null;
        }

        return self::$objectEncryptor->encryptString(self::$currentObjectId, $value);
    }
}
