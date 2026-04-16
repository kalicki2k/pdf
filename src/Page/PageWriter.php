<?php

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Resources\ResourcesWriter;

final readonly class PageWriter
{
    public function __construct(
        private ResourcesWriter $resourcesWriter = new ResourcesWriter(),
    ) {
    }

    public function write(Page $page): string
    {
        $mediaBox = sprintf(
            '[0 0 %s %s]',
            $this->formatNumber($page->mediaBox->width()),
            $this->formatNumber($page->mediaBox->height()),
        );

        return '<< /Type /Page'
            . ' /Parent ' . $page->parentObjectId . ' 0 R'
            . ' /MediaBox ' . $mediaBox
            . ' /Resources ' . $this->resourcesWriter->write($page->resources)
            . ' /Contents ' . $page->contentsObjectId . ' 0 R'
            . ' >>';
    }

    private function formatNumber(float $value): string
    {
        $formatted = sprintf('%.3F', $value)
                |> (fn($x) => rtrim($x, '0'))
                |> (fn($x) => rtrim($x, '.'));

        return $formatted === '' ? '0' : $formatted;
    }
}