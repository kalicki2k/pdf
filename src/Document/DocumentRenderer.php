<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Writer\Output;
use Kalle\Pdf\Writer\Renderer;

/**
 * Renders a prepared document to an output target.
 */
final readonly class DocumentRenderer
{
    public function __construct(
        private DocumentSerializationPlanBuilder $planBuilder = new DocumentSerializationPlanBuilder(),
        private Renderer $renderer = new Renderer(),
    ) {
    }

    public function write(Document $document, Output $output): void
    {
        $this->renderer->write(
            $this->planBuilder->build($document),
            $output,
        );
    }
}
