<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Preparation;

use Kalle\Pdf\Document\Document;

class DocumentRenderPreparer
{
    public function __construct(
        private readonly DocumentRenderLifecycle $renderLifecycle = new DocumentRenderLifecycle(),
    ) {
    }

    public function prepare(Document $document): void
    {
        $this->renderLifecycle->applyDeferredRenderFinalizers($document->getDeferredRendering());
        $this->renderLifecycle->applyDeferredPageDecorators(
            $document->getDeferredRendering(),
            $document->getPages(),
            static function (callable $renderer) use ($document): void {
                $document->renderInArtifactContext($renderer);
            },
        );
        $this->renderLifecycle->assertRenderRequirements(
            $document->getProfile(),
            $document->getTitle(),
            $document->getLanguage(),
            $document->hasStructure(),
        );
    }
}
