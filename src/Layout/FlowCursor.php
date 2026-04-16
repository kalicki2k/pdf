<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

use Kalle\Pdf\Page\PageContentArea;

final readonly class FlowCursor
{
    public static function startAtTop(PageContentArea $contentArea): self
    {
        return new self($contentArea->top);
    }

    public static function at(float $y): self
    {
        return new self($y);
    }

    private function __construct(
        public float $y,
    )
    {
    }

    public function movedTo(float $y): self
    {
        return new self($y);
    }

    public function movedDown(float $distance): self
    {
        return new self($this->y - $distance);
    }
}