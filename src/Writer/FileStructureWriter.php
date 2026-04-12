<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

use Kalle\Pdf\Debug\Debugger;

/**
 * Writes PDF file structure bytes such as the header, cross-reference table and trailer.
 */
final class FileStructureWriter
{
    /**
     * Writes the PDF file header.
     */
    public function writeHeader(FileStructure $fileStructure, Output $output): void
    {
        $output->write('%PDF-' . number_format($fileStructure->version, 1, '.', '') . "\n");
        $output->write("%\xE2\xE3\xCF\xD3\n");
    }

    /**
     * @param array<int, int> $offsets
     */
    public function writeFooter(FileStructure $fileStructure, array $offsets, Output $output, ?Debugger $debugger = null): void
    {
        $debugger ??= Debugger::disabled();
        $startXref = $output->offset();
        $trailer = $fileStructure->trailer;

        $output->write("xref\n");
        $output->write('0 ' . $trailer->size . "\n");
        $output->write("0000000000 65535 f \n");

        for ($objectId = 1; $objectId < $trailer->size; $objectId++) {
            $offset = $offsets[$objectId] ?? 0;
            $output->write(sprintf("%010d 00000 n \n", $offset));
        }

        $debugger->pdf('xref.written', [
            'object_count' => $trailer->size - 1,
            'size' => $trailer->size,
            'start_offset' => $startXref,
        ]);

        $output->write("trailer\n");
        $output->write('<< /Size ' . $trailer->size . ' /Root ' . $trailer->rootObjectId . ' 0 R');

        if ($trailer->infoObjectId !== null) {
            $output->write(' /Info ' . $trailer->infoObjectId . ' 0 R');
        }

        if ($trailer->encryptObjectId !== null) {
            $output->write(' /Encrypt ' . $trailer->encryptObjectId . ' 0 R');
        }

        if ($trailer->documentId !== null) {
            $output->write(' /ID [<' . $trailer->documentId . '> <' . $trailer->documentId . '>]');
        }

        $output->write(" >>\n");
        $output->write("startxref\n");
        $output->write((string) $startXref . "\n");
        $output->write('%%EOF');

        $debugger->pdf('trailer.written', [
            'root_id' => $trailer->rootObjectId,
            'info_id' => $trailer->infoObjectId,
            'size' => $trailer->size,
            'start_offset' => $startXref,
        ]);
    }
}
