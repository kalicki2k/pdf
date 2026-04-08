<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\EncryptDictionary;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;

class PdfRenderer
{
    private const string BINARY_HEADER_COMMENT = "%\xE2\xE3\xCF\xD3";

    public function render(Document $document): string
    {
        $output = new StringPdfOutput();
        $this->write($document, $output);

        return $output->contents();
    }

    public function write(Document $document, PdfOutput $output): void
    {
        $output->write('%PDF-' . $this->formatVersion($document->getVersion()) . PHP_EOL);
        $output->write(self::BINARY_HEADER_COMMENT . PHP_EOL);
        $offsets = [];
        $objectEncryptor = $this->buildObjectEncryptor($document);
        RenderContext::setObjectEncryptor($objectEncryptor);

        try {
            foreach ($document->getDocumentObjects() as $object) {
                RenderContext::enterObject($object->id);
                $offsets[$object->id] = $output->offset();
                $renderedObject = $object->render();

                if (
                    $objectEncryptor !== null
                    && !$object instanceof EncryptDictionary
                ) {
                    $renderedObject = $objectEncryptor->encryptStreamObject($renderedObject, $object->id);
                }

                $output->write($renderedObject);
                RenderContext::leaveObject();
            }
        } finally {
            RenderContext::leaveObject();
            RenderContext::setObjectEncryptor(null);
        }

        $startxref = $output->offset();
        $output->write($this->generateCrossReferenceTable($offsets));
        $objectIds = array_keys($offsets);
        $maxObjectId = max($objectIds ?: [0]);

        $output->write($this->generateTrailer(
            $maxObjectId + 1,
            $document->catalog->id,
            $document->shouldWriteInfoDictionary() ? $document->info->id : null,
            $document->encryptDictionary?->id,
            $document->getDocumentId(),
        ));
        $output->write('startxref' . PHP_EOL . $startxref . PHP_EOL . '%%EOF');
    }

    private function formatVersion(float $version): string
    {
        return number_format($version, 1, '.', '');
    }

    private function buildObjectEncryptor(Document $document): ?StandardObjectEncryptor
    {
        $profile = $document->getEncryptionProfile();
        $securityHandlerData = $document->getSecurityHandlerData();

        if ($profile === null || $securityHandlerData === null) {
            return null;
        }

        $objectEncryptor = new StandardObjectEncryptor($profile, $securityHandlerData);

        return $objectEncryptor->supportsObjectEncryption() ? $objectEncryptor : null;
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
