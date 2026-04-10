<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Text\StructureTag;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Image;
use Kalle\Pdf\Internal\Page\Content\Instruction\DrawImageInstruction;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Model\Page\ImageOptions;

/**
 * @internal Coordinates image rendering for a page.
 */
final class PageImages
{
    public static function forPage(Page $page, PageMarkedContentIds $pageMarkedContentIds): self
    {
        return new self(
            $page,
            $pageMarkedContentIds,
            new PageImageObjectFactory($page->getDocument()),
        );
    }

    public function __construct(
        private readonly Page $page,
        private readonly PageMarkedContentIds $pageMarkedContentIds,
        private readonly PageImageObjectFactory $imageObjectFactory,
    ) {
    }

    public function addImage(
        Image $image,
        Position $position,
        ?float $width = null,
        ?float $height = null,
        ImageOptions $options = new ImageOptions(),
    ): Page {
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException('Image width must be greater than zero.');
        }

        if ($height !== null && $height <= 0) {
            throw new InvalidArgumentException('Image height must be greater than zero.');
        }

        if ($options->structureTag !== null) {
            $this->page->getDocument()->ensureStructureEnabled();
        }

        $width ??= $image->getWidth();
        $height ??= $image->getHeight();

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Image dimensions must be greater than zero.');
        }

        if ($image->getSoftMask() !== null) {
            $this->page->getDocument()->assertAllowsTransparency();
        }

        $imageObject = $this->imageObjectFactory->create($image);
        $resourceName = $this->page->addImageResource($imageObject);
        $artifactContext = $options->structureTag === null && $this->page->getDocument()->isRenderingArtifactContext();
        $this->assertAllowsImageAccessibility($options, $artifactContext);
        $artifactTag = $artifactContext ? 'Artifact' : null;
        $contentTag = $options->structureTag !== null
            ? $options->structureTag->value
            : $artifactTag;
        $markedContentId = $options->structureTag !== null ? $this->nextMarkedContentId() : null;

        $this->page->addContentElement(new DrawImageInstruction(
            $resourceName,
            $position->x,
            $position->y,
            $width,
            $height,
            $markedContentId,
            $contentTag,
        ));

        if ($options->structureTag !== null && $markedContentId !== null) {
            $structElem = $this->page->getDocument()->createStructElem(
                $options->structureTag,
                $markedContentId,
                $this->page,
                $options->parentStructElem,
            );

            if ($options->altText !== null && $options->altText !== '') {
                $structElem->setAltText($options->altText);
            }
        }

        return $this->page;
    }

    private function assertAllowsImageAccessibility(ImageOptions $options, bool $artifactContext): void
    {
        $profile = $this->page->getDocument()->getProfile();

        if (!$profile->requiresTaggedImages()) {
            return;
        }

        if ($artifactContext) {
            return;
        }

        if ($options->structureTag !== StructureTag::Figure) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires images to be tagged as Figure or rendered as artifacts in the current implementation.',
                $profile->name(),
            ));
        }

        if (!$profile->requiresFigureAltText()) {
            return;
        }

        if ($options->altText !== null && $options->altText !== '') {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires alt text for Figure images in the current implementation.',
            $profile->name(),
        ));
    }
    private function nextMarkedContentId(): int
    {
        return $this->pageMarkedContentIds->next();
    }
}
