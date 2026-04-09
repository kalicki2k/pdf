<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Application\Document\DocumentAcroFormManager as ApplicationDocumentAcroFormManager;

/**
 * @internal Creates and reuses the document-wide AcroForm while guarding feature support.
 */
final readonly class DocumentAcroFormManager extends ApplicationDocumentAcroFormManager
{
}
