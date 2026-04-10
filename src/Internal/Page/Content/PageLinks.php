<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Internal\Page\Annotation\PageAnnotations;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Page;

/**
 * @internal Coordinates link annotations and link-related structure handling for a page.
 */
final class PageLinks
{
    public function __construct(
        private readonly Page $page,
        private readonly PageAnnotations $pageAnnotations,
        private readonly int $structParentId,
    ) {
    }

    public static function forPage(Page $page, PageAnnotations $pageAnnotations): self
    {
        return new self($page, $pageAnnotations, $page->structParentId);
    }

    public function addLink(
        Rect $box,
        string $url,
        ?string $accessibleName = null,
    ): void {
        if (str_starts_with($url, '#')) {
            $this->addInternalLink($box, substr($url, 1), $accessibleName);

            return;
        }

        if ($url === '') {
            throw new InvalidArgumentException('Link URL must not be empty.');
        }

        $this->addLinkTarget($box, LinkTarget::externalUrl($url), alternativeDescription: $accessibleName);
    }

    public function addInternalLink(
        Rect $box,
        string $destination,
        ?string $accessibleName = null,
    ): void {
        if ($destination === '') {
            throw new InvalidArgumentException('Link destination must not be empty.');
        }

        $this->addLinkTarget($box, LinkTarget::namedDestination($destination), alternativeDescription: $accessibleName);
    }

    public function addLinkTarget(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): void {
        $profile = $this->page->getDocument()->getProfile();

        if ($profile->requiresTaggedLinkAnnotations() && $linkStructElem === null) {
            if ($alternativeDescription === null || $alternativeDescription === '') {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires an accessible name for standalone link annotations.',
                    $profile->name(),
                ));
            }

            $linkStructElem = $this->page->getDocument()->createStructElem(StructureTag::Link);
            $linkStructElem->setPage($this->page);
        }

        $this->pageAnnotations->addLinkAnnotation($box, $target, $linkStructElem, $alternativeDescription);
    }

    public function resolveMarkedContentStructureTag(TextOptions $options): ?StructureTag
    {
        $profile = $this->page->getDocument()->getProfile();

        if ($options->link === null || !$profile->requiresTaggedLinkAnnotations()) {
            return $options->structureTag;
        }

        return StructureTag::Link;
    }

    public function attachTextToStructure(TextOptions $options, StructureTag $tag, int $markedContentId, string $text): StructElem
    {
        $profile = $this->page->getDocument()->getProfile();

        if ($options->link === null || !$profile->requiresTaggedLinkAnnotations()) {
            if ($options->parentStructElem !== null && $options->parentStructElem->tag() === $tag->value) {
                $options->parentStructElem->setMarkedContent($markedContentId, $this->page);
                $this->page->getDocument()->registerMarkedContentStructElem($this->structParentId, $options->parentStructElem);

                return $options->parentStructElem;
            }

            return $this->page->getDocument()->createStructElem($tag, $markedContentId, $this->page, $options->parentStructElem);
        }

        if ($options->structureTag === null || $options->structureTag === StructureTag::Link) {
            $linkStructElem = $this->page->getDocument()->createStructElem(StructureTag::Link, $markedContentId, $this->page, $options->parentStructElem);

            return $this->applyLinkAlternativeDescription($linkStructElem, $text);
        }

        $containerStructElem = $this->page->getDocument()->createStructElem($options->structureTag, parent: $options->parentStructElem);
        $linkStructElem = $this->page->getDocument()->createStructElem(StructureTag::Link, $markedContentId, $this->page, $containerStructElem);

        return $this->applyLinkAlternativeDescription($linkStructElem, $text);
    }

    public function resolveLinkAlternativeDescription(string $text): ?string
    {
        if (!$this->page->getDocument()->getProfile()->requiresLinkAnnotationAlternativeDescriptions()) {
            return null;
        }

        return $text !== '' ? $text : null;
    }

    private function applyLinkAlternativeDescription(StructElem $linkStructElem, string $text): StructElem
    {
        $alternativeDescription = $this->resolveLinkAlternativeDescription($text);

        if ($alternativeDescription !== null) {
            $linkStructElem->setAltText($alternativeDescription);
        }

        return $linkStructElem;
    }
}
