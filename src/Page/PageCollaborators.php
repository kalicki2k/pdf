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
        return $this->fonts ??= PageFonts::forPage($this->page);
    }

    public function objectRenderer(): PageObjectRenderer
    {
        return $this->objectRenderer ??= PageObjectRenderer::forPage($this->page);
    }

    public function graphics(): PageGraphics
    {
        return $this->graphics ??= PageGraphics::forPage($this->page);
    }

    public function components(): PageComponents
    {
        return $this->components ??= PageComponents::forPage(
            $this->links(),
            $this->graphics(),
            $this->fonts(),
            $this->textElementRenderer(),
            $this->paragraphRenderer(),
            $this->page->getDocument()->getProfile()->requiresTaggedPdf(),
            $this->page->getDocument()->getProfile()->requiresTaggedLinkAnnotations(),
        );
    }

    public function annotations(): PageAnnotations
    {
        return $this->annotations ??= PageAnnotations::forPage($this->page, $this->fonts());
    }

    public function existingAnnotations(): ?PageAnnotations
    {
        return $this->annotations;
    }

    public function images(): PageImages
    {
        return $this->images ??= PageImages::forPage($this->page, $this->markedContentIds());
    }

    public function links(): PageLinks
    {
        return $this->links ??= PageLinks::forPage($this->page, $this->annotations());
    }

    public function forms(): PageForms
    {
        return $this->forms ??= PageForms::forPage($this->page, $this->annotations(), $this->fonts());
    }

    public function layers(): PageLayers
    {
        return $this->layers ??= PageLayers::forPage($this->page);
    }

    public function textElementRenderer(): PageTextElementRenderer
    {
        return $this->textElementRenderer ??= PageTextElementRenderer::forPage(
            $this->page,
            $this->fonts(),
            $this->links(),
            $this->graphics(),
            $this->markedContentIds(),
        );
    }

    public function paragraphRenderer(): PageParagraphRenderer
    {
        return $this->paragraphRenderer ??= PageParagraphRenderer::forPage($this->page, $this->fonts());
    }
}
