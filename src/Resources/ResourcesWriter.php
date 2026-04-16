<?php

declare(strict_types=1);

namespace Kalle\Pdf\Resources;

final readonly class ResourcesWriter
{
    public function write(Resources $resources): string
    {
        $entries = [];

        if ($resources->fontObjectIds !== []) {
            $fontEntries = [];

            foreach ($resources->fontObjectIds as $name => $objectId) {
                $fontEntries[] = '/' . $name . ' ' . $objectId . ' 0 R';
            }

            $entries[] = '/Font << ' . implode(' ', $fontEntries) . ' >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }
}
