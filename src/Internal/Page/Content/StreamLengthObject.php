<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use Kalle\Pdf\Internal\Object\IndirectObject;
use Kalle\Pdf\Internal\Render\PdfOutput;

final class StreamLengthObject extends IndirectObject
{
    private int $length = 0;

    public function setLength(int $length): void
    {
        $this->length = $length;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    protected function writeObject(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write((string) $this->length . PHP_EOL);
        $output->write('endobj' . PHP_EOL);
    }
}
