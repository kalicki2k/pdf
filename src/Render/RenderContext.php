<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;

final class RenderContext
{
    private static ?self $current = null;

    private ?int $currentObjectId = null;

    private function __construct(private readonly ?StandardObjectEncryptor $objectEncryptor)
    {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runWith(?StandardObjectEncryptor $objectEncryptor, callable $callback): mixed
    {
        $previousContext = self::$current;
        self::$current = new self($objectEncryptor);

        try {
            return $callback();
        } finally {
            self::$current = $previousContext;
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runInObject(int $objectId, callable $callback): mixed
    {
        if (self::$current === null) {
            return $callback();
        }

        $previousObjectId = self::$current->currentObjectId;
        self::$current->currentObjectId = $objectId;

        try {
            return $callback();
        } finally {
            self::$current->currentObjectId = $previousObjectId;
        }
    }

    public static function encryptString(string $value): ?string
    {
        if (
            self::$current === null
            || self::$current->objectEncryptor === null
            || self::$current->currentObjectId === null
        ) {
            return null;
        }

        return self::$current->objectEncryptor->encryptString(self::$current->currentObjectId, $value);
    }
}
