<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

final readonly class PackedMonochromeBitmap
{
    public function __construct(
        public int $width,
        public int $height,
        public string $data,
    ) {
    }
}
