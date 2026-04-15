<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;

final class PermissionBitsResolver
{
    public function resolve(Permissions $permissions, EncryptionProfile $profile): int
    {
        if ($profile->revision < 3 || $profile->revision > 5) {
            throw new InvalidArgumentException('Unsupported encryption revision for permission resolution.');
        }

        $value = -64;

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
