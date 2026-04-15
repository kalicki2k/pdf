<?php

namespace Kalle\Pdf;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageRenderer;

/**
 * Renders a document model into a minimal PDF file.
 */
final readonly class PdfRenderer
{
    public function __construct(
        private PageRenderer $pageRenderer = new PageRenderer(),
    ) {
    }

    public function render(Document $document): string
    {
        $objects = [];
        $pageCount = count($document->pages);

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pageObjectNumbers = [];
        $contentObjectNumbers = [];
        $nextObjectNumber = 4;

        foreach ($document->pages as $page) {
            $pageObjectNumbers[] = $nextObjectNumber++;
            $contentObjectNumbers[] = $nextObjectNumber++;
        }

        $objects[2] = $this->renderPagesObject($pageObjectNumbers, $pageCount);

        foreach ($document->pages as $index => $page) {
            $pageObjectNumber = $pageObjectNumbers[$index];
            $contentObjectNumber = $contentObjectNumbers[$index];

            $contentStream = $this->pageRenderer->render($page);

            $objects[$pageObjectNumber] = $this->renderPageObject($page, $contentObjectNumber);
            $objects[$contentObjectNumber] = $this->renderContentObject($contentStream);
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

    /**
     * @param list<int> $pageObjectNumbers
     */
    private function renderPagesObject(array $pageObjectNumbers, int $pageCount): string
    {
        $kids = implode(' ', array_map(
            static fn (int $objectNumber): string => $objectNumber . ' 0 R',
            $pageObjectNumbers,
        ));

        return "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>";
    }

    private function renderPageObject(Page $page, int $contentObjectNumber): string
    {
        $mediaBox = sprintf(
            '[0 0 %s %s]',
            $this->formatNumber($page->pageSize->width()),
            $this->formatNumber($page->pageSize->height()),
        );

        return "<< /Type /Page /Parent 2 0 R /MediaBox {$mediaBox} /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
    }

    private function renderContentObject(string $contentStream): string
    {
        return '<< /Length ' . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream";
    }

    private function formatNumber(float $value): string
    {
        $formatted = sprintf('%.3F', $value)
                |> (fn ($x) => rtrim($x, '0'))
                |> (fn ($x) => rtrim($x, '.'));

        return $formatted === '' ? '0' : $formatted;
    }
}
