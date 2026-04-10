<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use Kalle\Pdf\Internal\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Internal\Page\Content\Instruction\RawInstruction;
use Kalle\Pdf\Internal\Page\Page;

/**
 * @internal Coordinates optional-content layer scopes for a page.
 */
final class PageLayers
{
    public function __construct(private readonly Page $page)
    {
    }

    public static function forPage(Page $page): self
    {
        return new self($page);
    }

    /**
     * @param callable(Page): void $renderer
     */
    public function layer(string | OptionalContentGroup $layer, callable $renderer, bool $visibleByDefault = true): Page
    {
        $group = is_string($layer)
            ? $this->page->getDocument()->addLayer($layer, $visibleByDefault)
            : $this->page->getDocument()->addLayer($layer->getName(), $layer->isVisibleByDefault());
        $resourceName = $this->page->addPropertyResource($group);

        $this->page->addContentElement(new RawInstruction("/OC /$resourceName BDC"));

        try {
            $renderer($this->page);
        } finally {
            $this->page->addContentElement(new RawInstruction('EMC'));
        }

        return $this->page;
    }
}
