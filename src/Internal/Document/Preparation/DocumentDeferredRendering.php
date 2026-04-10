<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Preparation;

use Kalle\Pdf\Internal\Page\Page;

class DocumentDeferredRendering
{
    /** @var list<callable(Page, int, int): void> */
    private array $headerRenderers = [];

    /** @var list<callable(Page, int, int): void> */
    private array $footerRenderers = [];

    /** @var list<callable(): void> */
    private array $renderFinalizers = [];

    /**
     * @param callable(Page, int, int): void $renderer
     */
    public function addHeaderRenderer(callable $renderer): void
    {
        $this->headerRenderers = [...$this->headerRenderers, $renderer];
    }

    /**
     * @param callable(Page, int, int): void $renderer
     */
    public function addFooterRenderer(callable $renderer): void
    {
        $this->footerRenderers = [...$this->footerRenderers, $renderer];
    }

    /**
     * @param callable(): void $finalizer
     */
    public function registerRenderFinalizer(callable $finalizer): void
    {
        $this->renderFinalizers = [...$this->renderFinalizers, $finalizer];
    }

    /**
     * @return list<callable(Page, int, int): void>
     */
    public function releaseHeaderRenderers(): array
    {
        return $this->release($this->headerRenderers);
    }

    /**
     * @return list<callable(Page, int, int): void>
     */
    public function releaseFooterRenderers(): array
    {
        return $this->release($this->footerRenderers);
    }

    /**
     * @return list<callable(): void>
     */
    public function releaseRenderFinalizers(): array
    {
        return $this->release($this->renderFinalizers);
    }

    /**
     * @template T
     * @param list<T> $entries
     * @return list<T>
     */
    private function release(array &$entries): array
    {
        $releasedEntries = $entries;
        $entries = [];

        return $releasedEntries;
    }
}
