<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

final class MarkedContentReference extends IndirectObject
{

    public function render(): string
    {
        $output = "{$this->id} 0 obj" . PHP_EOL;
        $output .= "<< /Type /MCR" . PHP_EOL;
        $output .= "/MCID 0 >>" . PHP_EOL; // Todo: ContentId
        $output .= "endobj" . PHP_EOL;

        return $output;
    }
}
