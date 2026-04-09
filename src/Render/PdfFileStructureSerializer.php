<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

final class PdfFileStructureSerializer
{
    private const string BINARY_HEADER_COMMENT = "%\xE2\xE3\xCF\xD3";

    public function writeHeader(float $version, PdfOutput $output): void
    {
        $output->write('%PDF-' . number_format($version, 1, '.', '') . PHP_EOL);
        $output->write(self::BINARY_HEADER_COMMENT . PHP_EOL);
    }

    public function writeCrossReferenceTable(PdfObjectOffsets $offsets, PdfOutput $output): void
    {
        $output->write($this->generateCrossReferenceTable($offsets));
    }

    /**
     * @param array{string, string} $documentId
     */
    public function writeTrailer(
        PdfOutput $output,
        int $size,
        int $rootId,
        ?int $infoId,
        ?int $encryptId,
        array $documentId,
    ): void {
        $output->write($this->generateTrailer($size, $rootId, $infoId, $encryptId, $documentId));
    }

    public function writeFooter(PdfOutput $output, int $startXref): void
    {
        $output->write('startxref' . PHP_EOL . $startXref . PHP_EOL . '%%EOF');
    }

    private function generateCrossReferenceTable(PdfObjectOffsets $offsets): string
    {
        $offsetsByObjectId = $offsets->entries();
        $maxObjectId = $offsets->highestObjectId();

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

    /**
     * @param array{string, string} $documentId
     */
    private function generateTrailer(int $size, int $rootId, ?int $infoId, ?int $encryptId, array $documentId): string
    {
        $trailer = 'trailer' . PHP_EOL
            . "<< /Size $size" . PHP_EOL
            . "/Root $rootId 0 R";

        if ($infoId !== null) {
            $trailer .= PHP_EOL . "/Info $infoId 0 R";
        }

        if ($encryptId !== null) {
            $trailer .= PHP_EOL . "/Encrypt $encryptId 0 R";
        }

        $trailer .= PHP_EOL . "/ID [<{$documentId[0]}> <{$documentId[1]}>]";

        return $trailer . ' >>' . PHP_EOL;
    }
}
