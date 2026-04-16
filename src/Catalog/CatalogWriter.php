<?php

declare(strict_types=1);

namespace Kalle\Pdf\Catalog;

final readonly class CatalogWriter
{
    public function write(Catalog $catalog): string
    {
        $entries = [
            '/Type /Catalog',
            '/Pages ' . $catalog->pagesObjectId . ' 0 R',
        ];

        if ($catalog->pageLabelsObjectId !== null) {
            $entries[] = '/PageLabels ' . $catalog->pageLabelsObjectId . ' 0 R';
        }

        if ($catalog->namesObjectId !== null) {
            $entries[] = '/Names ' . $catalog->namesObjectId . ' 0 R';
        }

        if ($catalog->outlinesObjectId !== null) {
            $entries[] = '/Outlines ' . $catalog->outlinesObjectId . ' 0 R';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }
}