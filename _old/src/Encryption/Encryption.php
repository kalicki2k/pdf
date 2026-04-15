<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

final readonly class Encryption
{
    private function __construct(
        public Algorithm $algorithm,
        public string $userPassword,
        public string $ownerPassword,
        public Permissions $permissions = new Permissions(),
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

    public static function aes128(string $userPassword, ?string $ownerPassword = null): self
    {
        return new self(
            Algorithm::AES_128,
            $userPassword,
            $ownerPassword ?? $userPassword,
        );
    }

    public static function aes256(string $userPassword, ?string $ownerPassword = null): self
    {
        return new self(
            Algorithm::AES_256,
            $userPassword,
            $ownerPassword ?? $userPassword,
        );
    }

    public function withPermissions(Permissions $permissions): self
    {
        return new self(
            $this->algorithm,
            $this->userPassword,
            $this->ownerPassword,
            $permissions,
        );
    }
}
