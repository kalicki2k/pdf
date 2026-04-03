<?php

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Render\PdfRenderer;

class Document
{
    private int $objectId = 0;
    private float $version;
    private string $title;
    private string $author;
    private ?string $subject;
    private string $language;
    private array $fonts = [];
    private array $keywords = [];
    private array $structElems = [];
    private Catalog $catalog;
    private Info $info;
    private Pages $pages;
    private Resources $resources;
    private StructTreeRoot $structTreeRoot;

    public function __construct(
        float $version = 1.0,
        string $title = '',
        string $author = '',
        ?string $subject = '',
        string $language = ''
    )
    {
        $this->version = $version;
        $this->title = $title;
        $this->author = $author;
        $this->subject = $subject;
        $this->language = $language;

        $this->catalog = new Catalog(++$this->objectId, $this);
        $this->pages = new Pages(++$this->objectId, $this);

        if ($this->version >= 1.4) {
            $this->structTreeRoot = new StructTreeRoot(++$this->objectId);
            $structElem = new StructElem(++$this->objectId, 'Document');
            $this->structTreeRoot->addKid($structElem->getId());
            $this->structElems['document'] = $structElem;
        }

        $this->info = new Info(++$this->objectId, $this);
        $this->resources = new Resources(++$this->objectId);
    }

    /**
     * @return int
     */
    public function getUniqObjectId(): int
    {
        return ++$this->objectId;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getStructElems(): array
    {
        return $this->structElems;
    }

    public function getCatalog(): Catalog
    {
        return $this->catalog;
    }

    public function getInfo(): Info
    {
        return $this->info;
    }

    public function getPages(): Pages
    {
        return $this->pages;
    }

    public function getResources(): Resources
    {
        return $this->resources;
    }

    public function getStructTreeRoot(): StructTreeRoot
    {
        return $this->structTreeRoot;
    }

    public function getFonts(): array {
        return $this->fonts;
    }

    public function getDocumentObjects(): array
    {
        $objects = [];

        $objects[] = $this->catalog;
        $objects[] = $this->pages;

        if ($this->version >= 1.4) {
            $objects[] = $this->structTreeRoot;

            foreach ($this->structElems as $structElem) {
                $objects[] = $structElem;
            }
        }

        $objects[] = $this->info;
        $objects[] = $this->resources;

        foreach ($this->fonts as $font) {
            $objects[] = $font;
        }

        foreach ($this->pages->getPages() as $page) {
            $objects[] = $page;
            $objects[] = $page->getContents();
        }

        return $objects;
    }

    public function addPage(float $width = 210.0, float $height = 297.0): Page
    {
        return $this->pages->addPage(++$this->objectId, ++$this->objectId, $width, $height);
    }

    public function addFont(string $baseFont, string $subtype = 'Type1', string $encoding = 'WinAnsiEncoding'): self {
        $font = new Font(++$this->objectId, $baseFont, $subtype, $encoding, $this->version);

        $this->fonts[] = $font;
        $this->resources->addFont($font);

        return $this;
    }

    // TODO:
    public function addStructElem(string $tag, $kid): self
    {
        $structElem = new StructElem(++$this->objectId, $tag);
        $structElem->addKid($kid);

        $this->structElems[] = $structElem;
        $this->structElems['document']->addKid($structElem->getId());

        return $this;
    }

    public function addKeyword(string $keyword): self
    {
        $this->keywords[] = $keyword;
        return $this;
    }

    public function render(): string
    {
        $renderer = new PdfRenderer();
        return $renderer->render($this);
    }
}