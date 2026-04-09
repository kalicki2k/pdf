<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Model\Document\IccProfileStream as ModelIccProfileStream;
use RuntimeException;

final class IccProfileStream extends ModelIccProfileStream
{
    public static function fromPath(int $id, string $path, int $colorComponents = 3): self
    {
        try {
            $data = BinaryData::fromFile($path);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException("Unable to read ICC profile '$path'.");
        }

        return new self($id, $data, $colorComponents);
    }
}
