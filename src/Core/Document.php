<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Utilities\StringListNormalizer;

final class Document
{
    private int $objectId = 0;

    /** @var Font[] */
    public array $fonts = [];

    /** @var string[] */
    public array $keywords = [];

    /** @var StructElem[]  */
    private array $structElems = [];
    public Catalog $catalog;
    public Info $info;
    public Pages $pages;
    public StructTreeRoot $structTreeRoot;

    public function __construct(
        public readonly float   $version = 1.0,
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $subject = null,
        public readonly ?string $language = null,
    )
    {
        $this->catalog = new Catalog(++$this->objectId, $this);
        $this->pages = new Pages(++$this->objectId, $this);

        if ($this->version >= 1.4) {
            $this->structTreeRoot = new StructTreeRoot(++$this->objectId);
            $structElem = new StructElem(++$this->objectId, 'Document');
            $this->structTreeRoot->addKid($structElem->id);
            $this->structElems['document'] = $structElem;
        }

        $this->info = new Info(++$this->objectId, $this);
    }

    /**
     * @return int
     */
    public function getUniqObjectId(): int
    {
        return ++$this->objectId;
    }

    /**
     * @return IndirectObject[]
     */
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

        foreach ($this->fonts as $font) {
            $objects[] = $font;
        }
        foreach ($this->pages->pages as $page) {
            $objects[] = $page;
            $objects[] = $page->resources;
            $objects[] = $page->contents;
        }

        return $objects;
    }

    public function addPage(float $width = 210.0, float $height = 297.0): Page
    {
        return $this->pages->addPage(++$this->objectId, ++$this->objectId, ++$this->objectId, $width, $height);
    }

    public function addFont(string $baseFont, string $subtype = 'Type1', string $encoding = 'WinAnsiEncoding'): self
    {
        $font = new Font(++$this->objectId, $baseFont, $subtype, $encoding, $this->version);

        $this->fonts = [...$this->fonts, $font];

        return $this;
    }

    /**
     * @param string $tag
     * @param int $kid
     * @return $this
     */
    public function addStructElem(string $tag, int $kid): self
    {
        $structElem = new StructElem(++$this->objectId, $tag);
        $structElem->addKid($kid);

        $this->structElems[] = $structElem;
        $this->structElems['document']->addKid($structElem->id);

        return $this;
    }

    public function addKeyword(string $keyword): self
    {
        $this->keywords = StringListNormalizer::unique([...$this->keywords, $keyword]);

        return $this;
    }

    public function render(): string
    {
        $renderer = new PdfRenderer();
        return $renderer->render($this);
    }
}
