<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

final readonly class Encryption
{
    private function __construct(
        public Algorithm $algorithm,
        public string $userPassword,
        public string $ownerPassword,
    ) {
    }

    public static function rc4_128(string $userPassword, ?string $ownerPassword = null): self
    {
        return new self(
            Algorithm::RC4_128,
            $userPassword,
            $ownerPassword ?? $userPassword,
        );
    }
}
