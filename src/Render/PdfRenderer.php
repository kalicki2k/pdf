<?php

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Core\Document;

class PdfRenderer
{
    public function render(Document $document): string
    {
        $output = "%PDF-{$document->getVersion()}\n";
        $offsets = [];

        foreach ($document->getDocumentObjects() as $object) {
            $offsets[$object->getId()] = mb_strlen($output, '8bit');
            $output .= $object->render();
        }

        $startxref = mb_strlen($output, '8bit');
        $output .= $this->generateCrossReferenceTable($offsets);
        $output .= $this->generateTrailer(
            max(array_keys($offsets)) + 1,
            $document->getCatalog()->getId(),
            $document->getInfo()->getId()
        );
        $output .= "startxref\n{$startxref}\n%%EOF";

        return $output;
    }

    private function generateCrossReferenceTable(array $offsetsByObjectId): string
    {
        ksort($offsetsByObjectId);
        $maxObjectId = count($offsetsByObjectId) === 0 ? 0 : max(array_keys($offsetsByObjectId));

        $xref = "xref\n";
        $xref .= "0 " . ($maxObjectId + 1) . "\n";
        $xref .= "0000000000 65535 f \n";

        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            if (isset($offsetsByObjectId[$objectId])) {
                $xref .= sprintf("%010d 00000 n \n", $offsetsByObjectId[$objectId]);
                continue;
            }

            $xref .= "0000000000 65535 f \n";
        }

        return $xref;
    }

    private function generateTrailer(int $size, int $rootId, int $infoId): string
    {
        return "trailer\n<< /Size $size\n/Root $rootId 0 R\n/Info $infoId 0 R >>\n";
    }
}
