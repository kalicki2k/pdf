<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Catalog\Catalog;
use Kalle\Pdf\Catalog\CatalogWriter;
use Kalle\Pdf\Contents\Contents;
use Kalle\Pdf\Contents\ContentsWriter;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Font\Font;
use Kalle\Pdf\Font\FontWriter;
use Kalle\Pdf\Font\StandardFont\StandardFont;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageRenderer;
use Kalle\Pdf\Page\PageWriter;
use Kalle\Pdf\Pages\Pages;
use Kalle\Pdf\Pages\PagesWriter;
use Kalle\Pdf\Resources\Resources;

/**
 * Renders a document model into a minimal PDF file.
 */
final readonly class PdfRenderer
{
    public static function make(): self
    {
        return new self(
            catalogWriter: new CatalogWriter(),
            pagesWriter: new PagesWriter(),
            pageWriter: new PageWriter(),
            contentsWriter: new ContentsWriter(),
            fontWriter: new FontWriter(),
            pageRenderer: new PageRenderer(),
        );
    }

    public function __construct(
        private CatalogWriter $catalogWriter,
        private PagesWriter $pagesWriter,
        private PageWriter $pageWriter,
        private ContentsWriter $contentsWriter,
        private FontWriter $fontWriter,
        private PageRenderer $pageRenderer,
    ) {
    }


    public function render(Document $document): string
    {
        $objects = [];

        $catalogObjectId = 1;
        $pagesObjectId = 2;
        $fontObjectId = 3;

        $pageObjectIds = [];
        $contentsObjectIds = [];
        $nextObjectId = 4;

        foreach ($document->pages as $documentPage) {
            $pageObjectIds[] = $nextObjectId++;
            $contentsObjectIds[] = $nextObjectId++;
        }

        $catalog = Catalog::make(
            pagesObjectId: $pagesObjectId,
        );

        $pages = Pages::make($pageObjectIds);

        $font = Font::type1(StandardFont::HELVETICA);

        $objects[$catalogObjectId] = $this->catalogWriter->write($catalog);
        $objects[$pagesObjectId] = $this->pagesWriter->write($pages);
        $objects[$fontObjectId] = $this->fontWriter->write($font);

        foreach ($document->pages as $index => $documentPage) {
            $contentStream = $this->pageRenderer->render($documentPage);
            $contents = Contents::make($contentStream);

            $page = Page::make(
                parentObjectId: $pagesObjectId,
                mediaBox: $documentPage->pageSize,
                resources: Resources::make([
                    'F1' => $fontObjectId,
                ]),
                contentsObjectId: $contentsObjectIds[$index],
            );

            $objects[$pageObjectIds[$index]] = $this->pageWriter->write($page);
            $objects[$contentsObjectIds[$index]] = $this->contentsWriter->write($contents);
        }

        ksort($objects);

        $pdf = "%PDF-1.7\n";
        $offsets = [0 => 0];

        foreach ($objects as $objectNumber => $body) {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= $objectNumber . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= '0 ' . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($objectNumber = 1; $objectNumber <= count($objects); $objectNumber++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$objectNumber]) . "\n";
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= '%%EOF';

        return $pdf;
    }
}
