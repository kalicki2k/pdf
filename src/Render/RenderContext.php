<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;

final class RenderContext
{
    private static ?self $current = null;

    private ?ObjectStringEncryptor $currentStringEncryptor = null;

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

        $previousStringEncryptor = self::$current->currentStringEncryptor;
        self::$current->currentStringEncryptor = self::$current->objectEncryptor === null
            ? null
            : new ObjectStringEncryptor(self::$current->objectEncryptor, $objectId);

        try {
            return $callback();
        } finally {
            self::$current->currentStringEncryptor = $previousStringEncryptor;
        }
    }

    public static function currentStringEncryptor(): ?ObjectStringEncryptor
    {
        return self::$current?->currentStringEncryptor;
    }
}
