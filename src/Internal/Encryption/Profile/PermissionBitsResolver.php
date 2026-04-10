<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Encryption\Profile;

use InvalidArgumentException;
use Kalle\Pdf\Security\EncryptionPermissions;

final class PermissionBitsResolver
{
    public function resolve(EncryptionPermissions $permissions, EncryptionProfile $profile): int
    {
        if ($profile->revision < 2 || $profile->revision > 6) {
            throw new InvalidArgumentException('Unsupported encryption revision for permission resolution.');
        }

        $value = -4;

        if ($permissions->print) {
            $value |= 1 << 2;
        }

        if ($permissions->modify) {
            $value |= 1 << 3;
        }

        if ($permissions->copy) {
            $value |= 1 << 4;
        }

        if ($permissions->annotate) {
            $value |= 1 << 5;
        }

        return $value;
    }
}
