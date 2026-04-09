<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Render\PdfOutput;

final class DocumentPdfWriter
{
    public function __construct(
        private readonly DocumentRenderPreparer $renderPreparer = new DocumentRenderPreparer(),
        private readonly DocumentPdfSerializer $pdfSerializer = new DocumentPdfSerializer(),
    ) {
    }

    public function write(Document $document, PdfOutput $output): void
    {
        $this->renderPreparer->prepare($document);
        $this->pdfSerializer->write($document, $output);
    }
}
