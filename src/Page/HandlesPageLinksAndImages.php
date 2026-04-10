<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Content\ImageOptions;

trait HandlesPageLinksAndImages
{
    public function addLink(
        Rect $box,
        string $url,
        ?string $accessibleName = null,
    ): self {
        $this->pageLinks()->addLink($box, $url, $accessibleName);

        return $this;
    }

    public function addInternalLink(
        Rect $box,
        string $destination,
        ?string $accessibleName = null,
    ): self {
        $this->pageLinks()->addInternalLink($box, $destination, $accessibleName);

        return $this;
    }

    public function addImage(
        Image $image,
        Position $position,
        ?float $width = null,
        ?float $height = null,
        ImageOptions $options = new ImageOptions(),
    ): self {
        return $this->pageImages()->addImage($image, $position, $width, $height, $options);
    }
}
