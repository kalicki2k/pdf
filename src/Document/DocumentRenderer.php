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
        $debugger = $document->debugger;
        $pageCount = count($document->pages);
        $scope = $debugger->startPerformanceScope('document.render', [
            'page_count' => $pageCount,
            'profile' => $document->profile->name(),
        ]);

        $debugger->lifecycle('write.started', [
            'title' => $document->title,
            'page_count' => $pageCount,
            'profile' => $document->profile->name(),
            'output' => $output::class,
        ]);

        $this->renderer->write($this->planBuilder->build($document), $output, $debugger);

        $scope->stop([
            'bytes' => $output->offset(),
            'page_count' => $pageCount,
        ]);

        $debugger->lifecycle('write.finished', [
            'title' => $document->title,
            'page_count' => $pageCount,
            'profile' => $document->profile->name(),
            'bytes' => $output->offset(),
            'output' => $output::class,
        ]);
    }
}
