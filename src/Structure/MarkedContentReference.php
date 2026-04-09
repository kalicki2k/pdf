<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use Kalle\Pdf\Object\IndirectObject;

final class MarkedContentReference extends IndirectObject
{
    protected function writeObject(\Kalle\Pdf\Render\PdfOutput $output): void
    {
        $output->write("{$this->id} 0 obj" . PHP_EOL);
        $output->write('<< /Type /MCR' . PHP_EOL);
        $output->write('/MCID 0 >>' . PHP_EOL); // Todo: ContentId
        $output->write('endobj' . PHP_EOL);
    }
}
