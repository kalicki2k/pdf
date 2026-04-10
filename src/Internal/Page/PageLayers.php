<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page;

use Kalle\Pdf\Element\Raw;
use Kalle\Pdf\Feature\OptionalContent\OptionalContentGroup;

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

        $this->page->addContentElement(new Raw("/OC /$resourceName BDC"));

        try {
            $renderer($this->page);
        } finally {
            $this->page->addContentElement(new Raw('EMC'));
        }

        return $this->page;
    }
}
