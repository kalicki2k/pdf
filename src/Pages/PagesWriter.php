<?php

declare(strict_types=1);

namespace Kalle\Pdf\Pages;

final readonly class PagesWriter
{
    public function write(Pages $pages): string
    {
        $kids = implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            $pages->kidsObjectIds,
        ));

        return '<< /Type /Pages /Kids [' . $kids . '] /Count ' . $pages->count . ' >>';
    }
}