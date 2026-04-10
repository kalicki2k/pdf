<?php

declare(strict_types=1);

namespace Kalle\Pdf\Security;

use InvalidArgumentException;

final readonly class EncryptionOptions
{
    public function __construct(
        public string $userPassword,
        public string $ownerPassword,
        public EncryptionPermissions $permissions = new EncryptionPermissions(),
        public EncryptionAlgorithm $algorithm = EncryptionAlgorithm::AUTO,
    ) {
        if ($this->userPassword === '' && $this->ownerPassword === '') {
            throw new InvalidArgumentException('Either a user password or an owner password must be provided.');
        }
    }
}
