<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\TaggedPdf\ParentTree;
use Kalle\Pdf\TaggedPdf\StructElem;
use Kalle\Pdf\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\TaggedPdf\StructureTag;

/**
 * @internal Manages document structure initialization and StructElem registration.
 */
class DocumentStructureManager
{
    /** @var StructElem[] */
    private array $structElems;

    /**
     * @param StructElem[] $structElems
     */
    public function __construct(
        private readonly Document $document,
        array &$structElems,
    ) {
        $this->structElems = & $structElems;
    }

    public function addStructElem(StructureTag $tag, int $markedContentId, ?Page $page = null): void
    {
        $this->createStructElem($tag, $markedContentId, $page);
    }

    public function createStructElem(
        StructureTag $tag,
        ?int $markedContentId = null,
        ?Page $page = null,
        ?StructElem $parent = null,
    ): StructElem {
        $this->ensureStructureEnabled();

        $structElem = new StructElem($this->document->getUniqObjectId(), $tag->value);
        ($parent ?? $this->structElems['document'])->addKid($structElem);

        $this->structElems[] = $structElem;

        if ($markedContentId !== null && $page !== null) {
            $structElem->setMarkedContent($markedContentId, $page);
        }

        if ($markedContentId !== null && $page !== null && $this->document->parentTree !== null) {
            $this->document->parentTree->add($page->structParentId, $structElem);
        }

        return $structElem;
    }

    public function registerObjectStructElem(int $structParentId, StructElem $structElem): void
    {
        if ($this->document->parentTree === null) {
            return;
        }

        $this->document->parentTree->addObject($structParentId, $structElem);
    }

    public function registerMarkedContentStructElem(int $structParentId, StructElem $structElem): void
    {
        if ($this->document->parentTree === null) {
            return;
        }

        $this->document->parentTree->add($structParentId, $structElem);
    }

    public function ensureStructureEnabled(): void
    {
        if (!$this->document->getProfile()->supportsStructure()) {
            throw new InvalidArgumentException('Structured content requires PDF version 1.4 or higher.');
        }

        if ($this->document->structTreeRoot !== null) {
            return;
        }

        $this->document->structTreeRoot = new StructTreeRoot($this->document->getUniqObjectId());
        $this->document->parentTree = new ParentTree($this->document->getUniqObjectId());
        $this->document->structTreeRoot->parentTree = $this->document->parentTree;

        $structElem = new StructElem($this->document->getUniqObjectId(), StructureTag::Document->value);
        $structElem->setParent($this->document->structTreeRoot);
        $this->document->structTreeRoot->addKid($structElem->id);
        $this->structElems['document'] = $structElem;
    }
}
