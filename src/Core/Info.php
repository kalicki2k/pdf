<?php

namespace Kalle\Pdf\Core;

use DateTime;
use Kalle\Pdf\Utilities\PdfStringEscaper;

class Info extends IndirectObject
{
    private Document $document;
    private string $producer;
    private string $creationDate;

    public function __construct(int $id, Document $document)
    {
        parent::__construct($id);

        $this->document = $document;
        $this->producer = 'swagPDF';
        $this->creationDate = (new DateTime())->format('YmdHis');
    }

    public function render(): string
    {
        $output = "{$this->id} 0 obj\n";
        $output .= "<<\n";
        $output .= "/Title (" . PdfStringEscaper::escape($this->document->getTitle()) . ")\n";
        $output .= "/Author (" . PdfStringEscaper::escape($this->document->getAuthor()) . ")\n";

        if (!empty($this->document->getSubject())) {
            $output .= "/Subject (" . PdfStringEscaper::escape($this->document->getSubject()) . ")\n";
        }

        if (!empty($this->document->getKeywords())) {
            $output .= "/Keywords (" . PdfStringEscaper::escape(implode(', ', $this->document->getKeywords())) . ")\n";
        }

        $output .= "/Creator (" . PdfStringEscaper::escape($this->producer) . ")\n";
        $output .= "/Producer (" . PdfStringEscaper::escape($this->producer) . ")\n";
        $output .= "/CreationDate (D:" . PdfStringEscaper::escape($this->creationDate) . ")\n";

        if ($this->document->getVersion() >= 1.4) {
            $output .= "/Lang (" . PdfStringEscaper::escape($this->document->getLanguage()) . ")\n";
        }

        $output .= ">>\n";
        $output .= "endobj\n";

        return $output;
    }
}
