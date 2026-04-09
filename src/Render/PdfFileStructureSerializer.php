<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

final class PdfFileStructureSerializer
{
    private const string BINARY_HEADER_COMMENT = "%\xE2\xE3\xCF\xD3";

    public function writeHeader(PdfFileStructure $fileStructure, PdfOutput $output): void
    {
        $output->write('%PDF-' . number_format($fileStructure->version, 1, '.', '') . PHP_EOL);
        $output->write(self::BINARY_HEADER_COMMENT . PHP_EOL);
    }

    public function writeCrossReferenceTable(PdfObjectOffsets $offsets, PdfOutput $output): void
    {
        $output->write($this->generateCrossReferenceTable($offsets));
    }

    public function writeCrossReferenceSection(
        PdfOutput $output,
        PdfObjectOffsets $offsets,
        PdfFileStructure $fileStructure,
    ): void {
        $startXref = $output->offset();

        $this->writeCrossReferenceTable($offsets, $output);
        $this->writeTrailer($output, $offsets, $fileStructure->trailer);
        $this->writeFooter($output, $startXref);
    }

    public function writeTrailer(
        PdfOutput $output,
        PdfObjectOffsets $offsets,
        PdfTrailer $trailer,
    ): void {
        $output->write($this->generateTrailer($offsets, $trailer));
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

    private function generateTrailer(PdfObjectOffsets $offsets, PdfTrailer $trailer): string
    {
        $trailerContents = 'trailer' . PHP_EOL
            . "<< /Size {$offsets->size()}" . PHP_EOL
            . "/Root {$trailer->rootObjectId} 0 R";

        if ($trailer->infoObjectId !== null) {
            $trailerContents .= PHP_EOL . "/Info {$trailer->infoObjectId} 0 R";
        }

        if ($trailer->encryptObjectId !== null) {
            $trailerContents .= PHP_EOL . "/Encrypt {$trailer->encryptObjectId} 0 R";
        }

        $trailerContents .= PHP_EOL . "/ID [<{$trailer->documentId[0]}> <{$trailer->documentId[1]}>]";

        return $trailerContents . ' >>' . PHP_EOL;
    }
}
