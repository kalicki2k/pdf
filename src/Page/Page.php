<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Image\ImageSource;

/**
 * Immutable page model for the document structure.
 */
final readonly class Page
{
    /**
     * @param array<string, PageFont> $fontResources
     * @param array<string, ImageSource> $imageResources
     * @param array<string, OptionalContentGroup> $optionalContentGroups
     * @param array<string, OptionalContentMembership> $optionalContentMemberships
     * @param list<PageImage> $images
     * @param list<PageAnnotation> $annotations
     * @param list<NamedDestination> $namedDestinations
     */
    public function __construct(
        public PageSize $size,
        public string $contents = '',
        public array $fontResources = [],
        public array $imageResources = [],
        public array $optionalContentGroups = [],
        public array $images = [],
        public array $annotations = [],
        public array $namedDestinations = [],
        public ?Margin $margin = null,
        public ?Color $backgroundColor = null,
        public ?string $label = null,
        public ?string $name = null,
        public array $optionalContentMemberships = [],
        public ?PageBox $cropBox = null,
        public ?PageBox $bleedBox = null,
        public ?PageBox $trimBox = null,
        public ?PageBox $artBox = null,
    ) {
        $this->cropBox?->assertFitsWithin($this->size);
        $this->bleedBox?->assertFitsWithin($this->size);
        $this->trimBox?->assertFitsWithin($this->size);
        $this->artBox?->assertFitsWithin($this->size);
    }

    public function contentArea(): ContentArea
    {
        $margin = $this->margin ?? Margin::all(0.0);

        return new ContentArea(
            left: $margin->left,
            right: $this->size->width() - $margin->right,
            top: $this->size->height() - $margin->top,
            bottom: $margin->bottom,
        );
    }
}
