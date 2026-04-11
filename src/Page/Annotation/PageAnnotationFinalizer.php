<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\TaggedPdf\StructElem;
use Kalle\Pdf\TaggedPdf\StructureTag;

final readonly class PageAnnotationFinalizer
{
    private const float MINIMUM_APPEARANCE_DIMENSION = 0.0001;

    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
    ) {
    }

    public function assertAllowsAnnotations(): void
    {
        $profile = $this->page->getDocument()->getProfile();

        if ($profile->supportsCurrentPageAnnotationsImplementation() || $profile->requiresTaggedPageAnnotations()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow page annotations in the current implementation.',
            $profile->name(),
        ));
    }

    public function assertAllowsLinkAnnotation(?StructElem $linkStructElem): void
    {
        $profile = $this->page->getDocument()->getProfile();

        if ($profile->supportsCurrentPageAnnotationsImplementation()) {
            return;
        }

        if ($profile->requiresTaggedLinkAnnotations() && $linkStructElem !== null) {
            return;
        }

        if ($profile->requiresTaggedLinkAnnotations()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s currently requires link annotations to be bound to tagged Link content.',
                $profile->name(),
            ));
        }

        $this->assertAllowsAnnotations();
    }

    public function assertRectHasPositiveDimensions(Rect $box, string $subject): void
    {
        if ($box->width <= 0) {
            throw new InvalidArgumentException("$subject width must be greater than zero.");
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException("$subject height must be greater than zero.");
        }
    }

    /**
     * @param IndirectObject&StructParentAwareAnnotation&AppearanceStreamAwareAnnotation $annotation
     */
    public function finalizeBoxAnnotation(
        IndirectObject & StructParentAwareAnnotation & AppearanceStreamAwareAnnotation $annotation,
        Rect $box,
        string $fallback,
        ?string ...$candidates,
    ): void {
        $this->attachBoxAppearanceIfRequired($annotation, $box);
        $this->bindAccessiblePageAnnotation($annotation, $this->resolveAlternativeDescription($fallback, ...$candidates));
    }

    /**
     * @param IndirectObject&StructParentAwareAnnotation&AppearanceStreamAwareAnnotation $annotation
     */
    public function finalizeLineAnnotation(
        IndirectObject & StructParentAwareAnnotation & AppearanceStreamAwareAnnotation $annotation,
        Position $from,
        Position $to,
        string $fallback,
        ?string ...$candidates,
    ): void {
        $this->attachLineAppearanceIfRequired($annotation, $from, $to);
        $this->bindAccessiblePageAnnotation($annotation, $this->resolveAlternativeDescription($fallback, ...$candidates));
    }

    /**
     * @param IndirectObject&StructParentAwareAnnotation&AppearanceStreamAwareAnnotation $annotation
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function finalizeVerticesAnnotation(
        IndirectObject & StructParentAwareAnnotation & AppearanceStreamAwareAnnotation $annotation,
        array $vertices,
        string $fallback,
        ?string ...$candidates,
    ): void {
        $this->attachVerticesAppearanceIfRequired($annotation, $vertices);
        $this->bindAccessiblePageAnnotation($annotation, $this->resolveAlternativeDescription($fallback, ...$candidates));
    }

    /**
     * @param IndirectObject&StructParentAwareAnnotation $annotation
     */
    public function finalizeAccessibleAnnotation(
        IndirectObject & StructParentAwareAnnotation $annotation,
        string $fallback,
        ?string ...$candidates,
    ): void {
        $this->bindAccessiblePageAnnotation($annotation, $this->resolveAlternativeDescription($fallback, ...$candidates));
    }

    private function attachBoxAppearanceIfRequired(AppearanceStreamAwareAnnotation $annotation, Rect $box): void
    {
        if (!$this->page->getDocument()->getProfile()->requiresAnnotationAppearanceStreams()) {
            return;
        }

        $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
    }

    private function attachLineAppearanceIfRequired(
        AppearanceStreamAwareAnnotation $annotation,
        Position $from,
        Position $to,
    ): void {
        if (!$this->page->getDocument()->getProfile()->requiresAnnotationAppearanceStreams()) {
            return;
        }

        $annotation->withAppearance($this->createAppearanceStream(abs($to->x - $from->x), abs($to->y - $from->y)));
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    private function attachVerticesAppearanceIfRequired(
        AppearanceStreamAwareAnnotation $annotation,
        array $vertices,
    ): void {
        if (!$this->page->getDocument()->getProfile()->requiresAnnotationAppearanceStreams()) {
            return;
        }

        $annotation->withAppearance($this->createAppearanceStreamForVertices($vertices));
    }

    private function createAppearanceStream(float $width, float $height): TextAnnotationAppearanceStream
    {
        return new TextAnnotationAppearanceStream(
            $this->context->nextObjectId(),
            max(self::MINIMUM_APPEARANCE_DIMENSION, $width),
            max(self::MINIMUM_APPEARANCE_DIMENSION, $height),
        );
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    private function createAppearanceStreamForVertices(array $vertices): TextAnnotationAppearanceStream
    {
        $xValues = array_map(static fn (array $vertex): float => $vertex[0], $vertices);
        $yValues = array_map(static fn (array $vertex): float => $vertex[1], $vertices);
        assert($xValues !== []);
        assert($yValues !== []);

        return $this->createAppearanceStream(
            max($xValues) - min($xValues),
            max($yValues) - min($yValues),
        );
    }

    /**
     * @param IndirectObject&StructParentAwareAnnotation $annotation
     */
    private function bindAccessiblePageAnnotation(
        IndirectObject & StructParentAwareAnnotation $annotation,
        string $alternativeDescription,
        StructureTag $tag = StructureTag::Annotation,
    ): void {
        $profile = $this->page->getDocument()->getProfile();

        if (!$profile->requiresTaggedPageAnnotations()) {
            return;
        }

        $structElem = $this->page->getDocument()->createStructElem($tag);
        $structElem->setPage($this->page);

        $structParentId = $this->page->getDocument()->getNextStructParentId();
        $annotation->withStructParent($structParentId);
        $structElem->addObjectReference($annotation, $this->page);

        if ($profile->requiresPageAnnotationAlternativeDescriptions() && $alternativeDescription !== '') {
            $structElem->setAltText($alternativeDescription);
        }

        $this->page->getDocument()->registerObjectStructElem($structParentId, $structElem);
    }

    private function resolveAlternativeDescription(string $fallback, ?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return $fallback;
    }
}
