<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Application\Document\DocumentFontFactory as ApplicationDocumentFontFactory;

/**
 * @internal Resolves document font registrations and creates the corresponding font objects.
 */
final readonly class DocumentFontFactory extends ApplicationDocumentFontFactory
{
}
