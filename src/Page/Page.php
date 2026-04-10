<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Content\Contents;
use Kalle\Pdf\Page\Resources\Resources;
use Kalle\Pdf\Render\PdfOutput;

class Page extends IndirectObject
{
    use HandlesPageAnnotations;
    use HandlesPageBuilders;
    use HandlesPageComponents;
    use HandlesPageContentsAndResources;
    use HandlesPageForms;
    use HandlesPageGraphics;
    use HandlesPageLinksAndImages;
    use HandlesPageTextLayout;

    private const float DEFAULT_BOTTOM_MARGIN = 20.0;

    private readonly PageCollaborators $collaborators;
    private readonly Contents $contents;
    private readonly Resources $resources;

    public function __construct(
        public int                $id,
        int                       $contentsId,
        int                       $resourcesId,
        public readonly int       $structParentId,
        private readonly float    $width,
        private readonly float    $height,
        private readonly Document $document,
    ) {
        parent::__construct($this->id);

        $this->contents = new Contents($contentsId);
        $this->resources = new Resources($resourcesId);
        $this->collaborators = new PageCollaborators($this);
    }

    /**
     * @param callable(self): void $renderer
     */
    public function layer(string | OptionalContentGroup $layer, callable $renderer, bool $visibleByDefault = true): self
    {
        return $this->collaborators->layers()->layer($layer, $renderer, $visibleByDefault);
    }

    protected function writeObject(PdfOutput $output): void
    {
        $this->collaborators->objectRenderer()->write($output, $this->collaborators->markedContentIds()->hasAllocatedIds());
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }
}
