<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Security;

final readonly class EncryptionPermissions
{
    public function __construct(
        public bool $print = true,
        public bool $modify = true,
        public bool $copy = true,
        public bool $annotate = true,
    ) {
    }

    public static function all(): self
    {
        return new self();
    }

    public static function readOnly(): self
    {
        return new self(
            print: false,
            modify: false,
            copy: false,
            annotate: false,
        );
    }
}
