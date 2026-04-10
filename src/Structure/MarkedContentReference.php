<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use Kalle\Pdf\Internal\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;

final class MarkedContentReference extends IndirectObject
{
    protected function writeObject(PdfOutput $output): void
    {
        $output->write("{$this->id} 0 obj" . PHP_EOL);
        $output->write('<< /Type /MCR' . PHP_EOL);
        $output->write('/MCID 0 >>' . PHP_EOL); // Todo: ContentId
        $output->write('endobj' . PHP_EOL);
    }
}
