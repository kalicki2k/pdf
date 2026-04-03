<?php

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Utilities\PdfStringEscaper;

class Catalog extends IndirectObject
{
    private Document $document;

    public function __construct(int $id, Document $document)
    {
        parent::__construct($id);

        $this->document = $document;
    }

    public function render(): string {
        $output = "{$this->id} 0 obj\n";
        $output .= "<< /Type /Catalog\n";

        if ($this->document->getVersion() >= 1.4) {
            $output .= "/MarkInfo << /Marked true >>\n";
            $output .= "/Lang (" . PdfStringEscaper::escape($this->document->getLanguage()) . ")\n";
            $output .= "/StructTreeRoot {$this->document->getStructTreeRoot()->getId()} 0 R\n";
        }

        $output .= "/Pages {$this->document->getPages()->getId()} 0 R >>\n";
        $output .= "endobj\n";

        return $output;
    }
}
