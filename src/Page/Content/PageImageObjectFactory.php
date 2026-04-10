<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content;

use Kalle\Pdf\Document;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Page\Resources\ImageObject;

/**
 * @internal Creates page image XObjects, including recursive soft-mask objects.
 */
final class PageImageObjectFactory
{
    public function __construct(
        private readonly Document $document,
    ) {
    }

    public function create(Image $image): ImageObject
    {
        $softMask = $image->getSoftMask();

        return new ImageObject(
            $this->document->getUniqObjectId(),
            $image,
            $softMask !== null ? $this->create($softMask) : null,
        );
    }
}
