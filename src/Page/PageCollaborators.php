<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Layout\Text\PageParagraphRenderer;
use Kalle\Pdf\Layout\Text\PageTextElementRenderer;
use Kalle\Pdf\Page\Annotation\PageAnnotations;
use Kalle\Pdf\Page\Content\PageComponents;
use Kalle\Pdf\Page\Content\PageGraphics;
use Kalle\Pdf\Page\Content\PageImages;
use Kalle\Pdf\Page\Content\PageLayers;
use Kalle\Pdf\Page\Content\PageLinks;
use Kalle\Pdf\Page\Content\PageMarkedContentIds;
use Kalle\Pdf\Page\Form\PageForms;
use Kalle\Pdf\Page\Resources\PageFonts;
use Kalle\Pdf\Page\Serialization\PageObjectRenderer;

final class PageCollaborators
{
    private ?PageComponents $components = null;
    private ?PageFonts $fonts = null;
    private ?PageGraphics $graphics = null;
    private ?PageImages $images = null;
    private ?PageLinks $links = null;
    private ?PageObjectRenderer $objectRenderer = null;
    private ?PageAnnotations $annotations = null;
    private ?PageForms $forms = null;
    private ?PageLayers $layers = null;
    private ?PageTextElementRenderer $textElementRenderer = null;
    private ?PageParagraphRenderer $paragraphRenderer = null;
    private readonly PageMarkedContentIds $markedContentIds;

    public function __construct(
        private readonly Page $page,
    ) {
        $this->markedContentIds = new PageMarkedContentIds();
    }

    public function markedContentIds(): PageMarkedContentIds
    {
        return $this->markedContentIds;
    }

    public function fonts(): PageFonts
    {
        return $this->fonts ??= $this->createFonts();
    }

    public function objectRenderer(): PageObjectRenderer
    {
        return $this->objectRenderer ??= $this->createObjectRenderer();
    }

    public function graphics(): PageGraphics
    {
        return $this->graphics ??= $this->createGraphics();
    }

    public function components(): PageComponents
    {
        return $this->components ??= $this->createComponents();
    }

    public function annotations(): PageAnnotations
    {
        return $this->annotations ??= $this->createAnnotations();
    }

    public function cachedAnnotations(): ?PageAnnotations
    {
        return $this->annotations;
    }

    public function images(): PageImages
    {
        return $this->images ??= $this->createImages();
    }

    public function links(): PageLinks
    {
        return $this->links ??= $this->createLinks();
    }

    public function forms(): PageForms
    {
        return $this->forms ??= $this->createForms();
    }

    public function layers(): PageLayers
    {
        return $this->layers ??= $this->createLayers();
    }

    public function textElementRenderer(): PageTextElementRenderer
    {
        return $this->textElementRenderer ??= $this->createTextElementRenderer();
    }

    public function paragraphRenderer(): PageParagraphRenderer
    {
        return $this->paragraphRenderer ??= $this->createParagraphRenderer();
    }

    private function createFonts(): PageFonts
    {
        return PageFonts::forPage($this->page);
    }

    private function createObjectRenderer(): PageObjectRenderer
    {
        return PageObjectRenderer::forPage($this->page);
    }

    private function createGraphics(): PageGraphics
    {
        return PageGraphics::forPage($this->page);
    }

    private function createComponents(): PageComponents
    {
        $profile = $this->page->getDocument()->getProfile();

        return PageComponents::forPage(
            $this->links(),
            $this->graphics(),
            $this->fonts(),
            $this->textElementRenderer(),
            $this->paragraphRenderer(),
            $profile->requiresTaggedPdf(),
            $profile->requiresTaggedLinkAnnotations(),
        );
    }

    private function createAnnotations(): PageAnnotations
    {
        return PageAnnotations::forPage($this->page, $this->fonts());
    }

    private function createImages(): PageImages
    {
        return PageImages::forPage($this->page, $this->markedContentIds());
    }

    private function createLinks(): PageLinks
    {
        return PageLinks::forPage($this->page, $this->annotations());
    }

    private function createForms(): PageForms
    {
        return PageForms::forPage($this->page, $this->annotations(), $this->fonts());
    }

    private function createLayers(): PageLayers
    {
        return PageLayers::forPage($this->page);
    }

    private function createTextElementRenderer(): PageTextElementRenderer
    {
        return PageTextElementRenderer::forPage(
            $this->page,
            $this->fonts(),
            $this->links(),
            $this->graphics(),
            $this->markedContentIds(),
        );
    }

    private function createParagraphRenderer(): PageParagraphRenderer
    {
        return PageParagraphRenderer::forPage($this->page, $this->fonts());
    }
}
