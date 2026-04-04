<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\Document;

class PdfRenderer
{
    public function render(Document $document): string
    {
        $output = "%PDF-{$document->version}" . PHP_EOL;
        $offsets = [];

        foreach ($document->getDocumentObjects() as $object) {
            $offsets[$object->id] = mb_strlen($output, '8bit');
            $output .= $object->render();
        }

        $startxref = mb_strlen($output, '8bit');
        $output .= $this->generateCrossReferenceTable($offsets);
        $objectIds = array_keys($offsets);
        $maxObjectId = max($objectIds ?: [0]);

        $output .= $this->generateTrailer(
            $maxObjectId + 1,
            $document->catalog->id,
            $document->info->id,
        );
        $output .= 'startxref' . PHP_EOL . $startxref . PHP_EOL . '%%EOF';

        return $output;
    }

    /**
     * @param int[] $offsetsByObjectId
     * @return string
     */
    private function generateCrossReferenceTable(array $offsetsByObjectId): string
    {
        ksort($offsetsByObjectId);
        $maxObjectId = count($offsetsByObjectId) === 0 ? 0 : max(array_keys($offsetsByObjectId));

        $xref = 'xref' . PHP_EOL;
        $xref .= '0 ' . ($maxObjectId + 1) . PHP_EOL;
        $xref .= '0000000000 65535 f ' . PHP_EOL;

        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            if (isset($offsetsByObjectId[$objectId])) {
                $xref .= sprintf('%010d 00000 n %s', $offsetsByObjectId[$objectId], PHP_EOL);
                continue;
            }

            $xref .= '0000000000 65535 f ' . PHP_EOL;
        }

        return $xref;
    }

    private function generateTrailer(int $size, int $rootId, int $infoId): string
    {
        return 'trailer' . PHP_EOL
            . "<< /Size $size" . PHP_EOL
            . "/Root $rootId 0 R" . PHP_EOL
            . "/Info $infoId 0 R >>" . PHP_EOL;
    }
}
