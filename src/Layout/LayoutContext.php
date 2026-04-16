<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

use Kalle\Pdf\Document\DocumentPage;
use Kalle\Pdf\Page\PageContentArea;

final readonly class LayoutContext
{
    public static function make(
        DocumentPage $documentPage,
        ?FlowCursor  $cursor = null,
    ): self
    {
        return new self(
            documentPage: $documentPage,
            contentArea: $documentPage->contentArea(),
            cursor: $cursor ?? FlowCursor::startAtTop($documentPage->contentArea())
        );
    }

    private function __construct(
        public DocumentPage    $documentPage,
        public PageContentArea $contentArea,
        public FlowCursor      $cursor,
    )
    {
    }
}