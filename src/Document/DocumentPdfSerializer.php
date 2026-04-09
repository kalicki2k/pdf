<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\PdfRenderer;

final class DocumentPdfSerializer
{
    public function __construct(
        private readonly DocumentSerializationPlanBuilder $serializationPlanBuilder = new DocumentSerializationPlanBuilder(),
        private readonly PdfRenderer $pdfRenderer = new PdfRenderer(),
    ) {
    }

    public function write(Document $document, PdfOutput $output): void
    {
        $this->pdfRenderer->write($this->serializationPlanBuilder->build($document), $output);
    }
}
