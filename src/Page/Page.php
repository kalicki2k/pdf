<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Table\Table as LayoutTable;
use Kalle\Pdf\Layout\Text\PageParagraphRenderer;
use Kalle\Pdf\Layout\Text\PageTextElementRenderer;
use Kalle\Pdf\Layout\Text\TextFrame as LayoutTextFrame;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Object\StreamLengthObject;
use Kalle\Pdf\Page\Annotation\PageAnnotation;
use Kalle\Pdf\Page\Annotation\PageAnnotations;
use Kalle\Pdf\Page\Content\Contents;
use Kalle\Pdf\Page\Content\Instruction\ContentInstruction;
use Kalle\Pdf\Page\Content\PageComponents;
use Kalle\Pdf\Page\Content\PageGraphics;
use Kalle\Pdf\Page\Content\PageImages;
use Kalle\Pdf\Page\Content\PageLayers;
use Kalle\Pdf\Page\Content\PageLinks;
use Kalle\Pdf\Page\Form\PageForms;
use Kalle\Pdf\Page\Resources\ImageObject;
use Kalle\Pdf\Page\Resources\PageFonts;
use Kalle\Pdf\Page\Resources\Resources;
use Kalle\Pdf\Page\Serialization\PageObjectRenderer;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\Table\Table;
use Kalle\Pdf\Text\TextFrame;

class Page extends IndirectObject
{
    use HandlesPageAnnotations;
    use HandlesPageComponents;
    use HandlesPageForms;
    use HandlesPageGraphics;
    use HandlesPageLinksAndImages;
    use HandlesPageTextLayout;

    private const float DEFAULT_BOTTOM_MARGIN = 20.0;

    private readonly PageCollaborators $collaborators;
    private readonly Contents $contents;
    private readonly Resources $resources;

    public function __construct(
        public int                $id,
        int                       $contentsId,
        int                       $resourcesId,
        public readonly int       $structParentId,
        private readonly float    $width,
        private readonly float    $height,
        private readonly Document $document,
    ) {
        parent::__construct($this->id);

        $this->contents = new Contents($contentsId);
        $this->resources = new Resources($resourcesId);
        $this->collaborators = new PageCollaborators($this);
    }

    /**
     * @param callable(self): void $renderer
     */
    public function layer(string | OptionalContentGroup $layer, callable $renderer, bool $visibleByDefault = true): self
    {
        return $this->pageLayers()->layer($layer, $renderer, $visibleByDefault);
    }

    public function createTextFrame(
        Position $position,
        float $width,
        float $bottomMargin = self::DEFAULT_BOTTOM_MARGIN,
    ): TextFrame {
        return new TextFrame(new LayoutTextFrame($this, $position->x, $position->y, $width, $bottomMargin));
    }

    /**
     * @param list<float|int> $columnWidths
     */
    public function createTable(
        Position $position,
        float $width,
        array $columnWidths,
        float $bottomMargin = self::DEFAULT_BOTTOM_MARGIN,
    ): Table {
        return new Table(new LayoutTable($this, $position->x, $position->y, $width, $columnWidths, $bottomMargin));
    }

    protected function writeObject(PdfOutput $output): void
    {
        $this->pageObjectRenderer()->write($output, $this->collaborators->markedContentIds()->hasAllocatedIds());
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getResources(): Resources
    {
        return $this->resources;
    }

    public function getContents(): Contents
    {
        return $this->contents;
    }

    public function prepareContentsLengthObject(): StreamLengthObject
    {
        $lengthObject = $this->contents->getLengthObject();

        if ($lengthObject !== null) {
            return $lengthObject;
        }

        return $this->contents->prepareLengthObject($this->document->getUniqObjectId());
    }

    public function addContentElement(ContentInstruction $element): void
    {
        $this->contents->addElement($element);
    }

    public function addFontResource(FontDefinition $font): string
    {
        return $this->resources->addFont($font);
    }

    public function addImageResource(ImageObject $image): string
    {
        return $this->resources->addImage($image);
    }

    public function addPropertyResource(OptionalContentGroup $group): string
    {
        return $this->resources->addProperty($group);
    }

    public function addOpacityResource(Opacity $opacity): string
    {
        return $this->resources->addOpacity($opacity);
    }

    /**
     * @return list<IndirectObject&PageAnnotation>
     */
    public function getAnnotations(): array
    {
        return $this->collaborators->existingAnnotations()?->all() ?? [];
    }

    private function pageFonts(): PageFonts
    {
        return $this->collaborators->fonts();
    }

    private function pageObjectRenderer(): PageObjectRenderer
    {
        return $this->collaborators->objectRenderer();
    }

    private function pageGraphics(): PageGraphics
    {
        return $this->collaborators->graphics();
    }

    private function pageComponents(): PageComponents
    {
        return $this->collaborators->components();
    }

    private function pageAnnotations(): PageAnnotations
    {
        return $this->collaborators->annotations();
    }

    private function pageImages(): PageImages
    {
        return $this->collaborators->images();
    }

    private function pageLinks(): PageLinks
    {
        return $this->collaborators->links();
    }

    private function pageForms(): PageForms
    {
        return $this->collaborators->forms();
    }

    private function pageLayers(): PageLayers
    {
        return $this->collaborators->layers();
    }

    private function pageTextElementRenderer(): PageTextElementRenderer
    {
        return $this->collaborators->textElementRenderer();
    }

    private function pageParagraphRenderer(): PageParagraphRenderer
    {
        return $this->collaborators->paragraphRenderer();
    }

}
