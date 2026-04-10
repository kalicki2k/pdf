<?php

declare(strict_types=1);

namespace Kalle\Pdf\Binary;

/**
 * @internal Models a binary source that supports random-access inspection in addition to streaming writes.
 */
interface RandomAccessBinaryDataSource extends BinaryDataSource
{
    public function length(): int;

    public function slice(int $offset, int $length): string;
}
