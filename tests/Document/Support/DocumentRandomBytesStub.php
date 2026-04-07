<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Random\RandomException;

final class DocumentRandomBytesStub
{
    public static bool $shouldThrow = false;
}

/**
 * @throws RandomException
 */
function random_bytes(int $length): string
{
    if (DocumentRandomBytesStub::$shouldThrow) {
        throw new RandomException('stubbed random_bytes failure');
    }

    return \random_bytes($length);
}
