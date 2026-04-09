<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Model\Page\ImageObject;

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
