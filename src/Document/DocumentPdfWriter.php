<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\PdfRenderer;

final class DocumentPdfWriter
{
    public function __construct(
        private readonly DocumentRenderPreparer $renderPreparer = new DocumentRenderPreparer(),
        private readonly DocumentSerializationPlanBuilder $serializationPlanBuilder = new DocumentSerializationPlanBuilder(),
        private readonly PdfRenderer $pdfRenderer = new PdfRenderer(),
    ) {
    }

    public function write(Document $document, PdfOutput $output): void
    {
        $this->renderPreparer->prepare($document);
        $this->pdfRenderer->write($this->serializationPlanBuilder->build($document), $output);
    }
}
