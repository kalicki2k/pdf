<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Security;

use Kalle\Pdf\Security\EncryptionPermissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptionPermissionsTest extends TestCase
{
    #[Test]
    public function it_creates_fully_enabled_permissions(): void
    {
        $permissions = EncryptionPermissions::all();

        self::assertTrue($permissions->print);
        self::assertTrue($permissions->modify);
        self::assertTrue($permissions->copy);
        self::assertTrue($permissions->annotate);
    }

    #[Test]
    public function it_creates_read_only_permissions(): void
    {
        $permissions = EncryptionPermissions::readOnly();

        self::assertFalse($permissions->print);
        self::assertFalse($permissions->modify);
        self::assertFalse($permissions->copy);
        self::assertFalse($permissions->annotate);
    }
}
