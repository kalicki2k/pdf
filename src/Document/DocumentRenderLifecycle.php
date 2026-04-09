<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Application\Document\DocumentRenderLifecycle as ApplicationDocumentRenderLifecycle;

/**
 * @internal Applies document render-time lifecycle steps before the PDF is serialized.
 */
final class DocumentRenderLifecycle extends ApplicationDocumentRenderLifecycle
{
}
