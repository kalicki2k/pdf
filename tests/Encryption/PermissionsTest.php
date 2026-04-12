<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Encryption;

use Kalle\Pdf\Encryption\Permissions;
use PHPUnit\Framework\TestCase;

final class PermissionsTest extends TestCase
{
    public function testItBuildsReadOnlyPermissions(): void
    {
        $permissions = Permissions::readOnly();

        self::assertFalse($permissions->print);
        self::assertFalse($permissions->modify);
        self::assertFalse($permissions->copy);
        self::assertFalse($permissions->annotate);
    }
}
