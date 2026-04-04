<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\CidToGidMap;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontDescriptor;
use Kalle\Pdf\Font\FontFileStream;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Structure\ParentTree;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructTreeRoot;
use Kalle\Pdf\Utilities\StringListNormalizer;

final class Document
{
    private int $objectId = 0;
    private int $structParentId = -1;
    /** @var list<callable(Page, int): void> */
    private array $headerRenderers = [];
    /** @var list<callable(Page, int): void> */
    private array $footerRenderers = [];

    /** @var array<int, FontDefinition&IndirectObject> */
    public array $fonts = [];

    /** @var string[] */
    public array $keywords = [];

    /** @var StructElem[]  */
    private array $structElems = [];
    public Catalog $catalog;
    public Info $info;
    public ?OutlineRoot $outlineRoot = null;
    public ?ParentTree $parentTree = null;
    public Pages $pages;
    public ?StructTreeRoot $structTreeRoot = null;

    /**
     * @param list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null $fontConfig
     */
    public function __construct(
        public readonly float   $version = 1.0,
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $subject = null,
        public readonly ?string $language = null,
        private readonly ?array $fontConfig = null,
    ) {
        $this->catalog = new Catalog(++$this->objectId, $this);
        $this->pages = new Pages(++$this->objectId, $this);

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
     * @return list<IndirectObject>
     */
    public function getDocumentObjects(): array
    {
        /** @var list<IndirectObject> $objects */
        $objects = [];

        $objects[] = $this->catalog;
        $objects[] = $this->pages;

        if ($this->outlineRoot !== null) {
            $objects[] = $this->outlineRoot;

            foreach ($this->outlineRoot->getItems() as $outlineItem) {
                $objects[] = $outlineItem;
            }
        }

        if ($this->structTreeRoot !== null) {
            $objects[] = $this->structTreeRoot;
        }

        if ($this->parentTree !== null) {
            $objects[] = $this->parentTree;
        }

        foreach ($this->structElems as $key => $structElem) {
            if ($key === 'document') {
                $objects[] = $structElem;
                continue;
            }

            $objects[] = $structElem;
        }

        $objects[] = $this->info;

        foreach ($this->fonts as $font) {
            if ($font instanceof UnicodeFont) {
                if ($font->descendantFont->fontDescriptor !== null) {
                    $objects[] = $font->descendantFont->fontDescriptor->fontFile;
                    $objects[] = $font->descendantFont->fontDescriptor;
                }

                if ($font->descendantFont->cidToGidMap !== null) {
                    $objects[] = $font->descendantFont->cidToGidMap;
                }

                $objects[] = $font->descendantFont;
                $objects[] = $font->toUnicode;
            }

            $objects[] = $font;
        }
        foreach ($this->pages->pages as $page) {
            $objects[] = $page;
            foreach ($page->getAnnotations() as $annotation) {
                $objects[] = $annotation;
            }
            foreach ($page->resources->getImages() as $image) {
                $objects[] = $image;
            }
            $objects[] = $page->resources;
            $objects[] = $page->contents;
        }

        return $objects;
    }

    public function addPage(PageSize | float $width = 595.2755905511812, ?float $height = null): Page
    {
        if ($width instanceof PageSize) {
            if ($height !== null) {
                throw new InvalidArgumentException('Height must not be provided when using a PageSize.');
            }

            $height = $width->height();
            $width = $width->width();
        }

        $height ??= 841.8897637795277;

        $page = $this->pages->addPage(++$this->objectId, ++$this->objectId, ++$this->objectId, ++$this->structParentId, $width, $height);
        $this->applyPageDecorators($page);

        return $page;
    }

    public function addOutline(string $title, Page $page): self
    {
        if ($title === '') {
            throw new InvalidArgumentException('Outline title must not be empty.');
        }

        $this->outlineRoot ??= new OutlineRoot(++$this->objectId);
        $this->outlineRoot->addItem(new OutlineItem(++$this->objectId, $this->outlineRoot, $title, $page));

        return $this;
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addHeader(callable $renderer): self
    {
        $this->headerRenderers[] = $renderer;

        return $this;
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addFooter(callable $renderer): self
    {
        $this->footerRenderers[] = $renderer;

        return $this;
    }

    public function addFont(
        string $baseFont,
        string $subtype = 'Type1',
        string $encoding = 'WinAnsiEncoding',
        bool $unicode = false,
        ?string $fontFilePath = null,
    ): self {
        if (FontRegistry::has($baseFont, $this->fontConfig)) {
            $preset = FontRegistry::get($baseFont, $this->fontConfig);
            $baseFont = $preset->baseFont;
            $subtype = $preset->subtype;
            $encoding = $preset->encoding;
            $unicode = $preset->unicode;
            $fontFilePath = $preset->path;
        }

        if ($unicode) {
            $fontDescriptor = null;
            $glyphMap = new UnicodeGlyphMap();
            $fontParser = null;
            $defaultWidth = 1000;
            $widths = [];

            if ($fontFilePath !== null) {
                $fontFile = FontFileStream::fromPath(++$this->objectId, $fontFilePath);
                $fontDescriptor = new FontDescriptor(++$this->objectId, $baseFont, $fontFile);
                $fontParser = new OpenTypeFontParser($fontFile->data);

                if ($fontParser->hasCffOutlines()) {
                    $subtype = 'CIDFontType0';
                }
            }

            $cidToGidMap = $fontParser !== null
                ? new CidToGidMap(++$this->objectId, $glyphMap, $fontParser)
                : null;

            $descendantFont = new CidFont(
                ++$this->objectId,
                $baseFont,
                $subtype,
                fontDescriptor: $fontDescriptor,
                cidToGidMap: $cidToGidMap,
                defaultWidth: $defaultWidth,
                widths: $widths,
            );
            $toUnicode = new ToUnicodeCMap(++$this->objectId, $glyphMap);
            $font = new UnicodeFont(++$this->objectId, $descendantFont, $toUnicode, $glyphMap);
        } else {
            $fontParser = null;

            if ($fontFilePath !== null) {
                $fontData = file_get_contents($fontFilePath);

                if ($fontData === false) {
                    throw new \InvalidArgumentException("Unable to read font file '$fontFilePath'.");
                }

                $fontParser = new OpenTypeFontParser($fontData);
            }

            $font = new StandardFont(++$this->objectId, $baseFont, $subtype, $encoding, $this->version, $fontParser);
        }

        $this->fonts = [...$this->fonts, $font];

        return $this;
    }

    /**
     * @return list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null
     */
    public function getFontConfig(): ?array
    {
        return $this->fontConfig;
    }

    /**
     * @param string $tag
     * @param int $markedContentId
     * @param Page|null $page
     * @return $this
     */
    public function addStructElem(string $tag, int $markedContentId, ?Page $page = null): self
    {
        $this->ensureStructureEnabled();

        $structElem = new StructElem(++$this->objectId, $tag);
        $this->structElems['document']->addKid($structElem);

        $this->structElems[] = $structElem;

        if ($page !== null) {
            $structElem->setMarkedContent($markedContentId, $page);
        }

        if ($page !== null && $this->parentTree !== null) {
            $this->parentTree->add($page->structParentId, $structElem);
        }

        return $this;
    }

    private function applyPageDecorators(Page $page): void
    {
        $pageNumber = count($this->pages->pages);

        foreach ($this->headerRenderers as $headerRenderer) {
            $headerRenderer($page, $pageNumber);
        }

        foreach ($this->footerRenderers as $footerRenderer) {
            $footerRenderer($page, $pageNumber);
        }
    }

    public function ensureStructureEnabled(): void
    {
        if ($this->version < 1.4) {
            throw new InvalidArgumentException('Structured content requires PDF version 1.4 or higher.');
        }

        if ($this->structTreeRoot !== null) {
            return;
        }

        $this->structTreeRoot = new StructTreeRoot(++$this->objectId);
        $this->parentTree = new ParentTree(++$this->objectId);
        $this->structTreeRoot->parentTree = $this->parentTree;
        $structElem = new StructElem(++$this->objectId, 'Document');
        $this->structTreeRoot->addKid($structElem->id);
        $this->structElems['document'] = $structElem;
    }

    public function hasStructure(): bool
    {
        return $this->structTreeRoot !== null;
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
