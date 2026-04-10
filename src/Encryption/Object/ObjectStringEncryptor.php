<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption\Object;

final readonly class ObjectStringEncryptor
{
    public function __construct(
        private StandardObjectEncryptor $objectEncryptor,
        private int $objectId,
    ) {
    }

    public function encrypt(string $value): string
    {
        return $this->objectEncryptor->encryptString($this->objectId, $value);
    }
}
