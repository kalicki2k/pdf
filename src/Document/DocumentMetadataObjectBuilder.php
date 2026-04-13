<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTimeImmutable;
use Kalle\Pdf\Document\Metadata\IccProfile;
use Kalle\Pdf\Document\Metadata\XmpMetadata;
use Kalle\Pdf\Writer\IndirectObject;

use function str_replace;

final readonly class DocumentMetadataObjectBuilder
{
    public function __construct(
        private DocumentMetadataInspector $metadataInspector = new DocumentMetadataInspector(),
        private DocumentInfoDictionaryBuilder $infoDictionaryBuilder = new DocumentInfoDictionaryBuilder(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function buildCatalogEntries(
        Document $document,
        ?int $metadataObjectId,
        ?int $iccProfileObjectId,
    ): array {
        $entries = [];

        if ($metadataObjectId !== null) {
            $entries[] = '/Metadata ' . $metadataObjectId . ' 0 R';
        }

        if ($iccProfileObjectId !== null) {
            $outputIntent = $this->metadataInspector->resolvePdfAOutputIntent($document);
            $entries[] = '/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier '
                . $this->pdfString($outputIntent->outputConditionIdentifier)
                . ($outputIntent->info !== null ? ' /Info ' . $this->pdfString($outputIntent->info) : '')
                . ' /DestOutputProfile '
                . $iccProfileObjectId
                . ' 0 R >>]';
        }

        return $entries;
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(
        Document $document,
        DocumentSerializationPlanBuildState $state,
        DateTimeImmutable $serializedAt,
        string $encryptObjectContents,
    ): array {
        $objects = [];

        if ($state->metadataObjectId !== null) {
            $xmpMetadata = new XmpMetadata();
            $objects[] = IndirectObject::stream(
                $state->metadataObjectId,
                $xmpMetadata->streamDictionaryContents($document, $serializedAt),
                $xmpMetadata->streamContents($document, $serializedAt),
            );
        }

        if ($state->iccProfileObjectId !== null) {
            $outputIntent = $this->metadataInspector->resolvePdfAOutputIntent($document);
            $iccProfile = IccProfile::fromPath($outputIntent->iccProfilePath, $outputIntent->colorComponents);
            $objects[] = IndirectObject::stream(
                $state->iccProfileObjectId,
                $iccProfile->streamDictionaryContents(),
                $iccProfile->streamContents(),
            );
        }

        if ($state->infoObjectId !== null) {
            $objects[] = IndirectObject::plain(
                $state->infoObjectId,
                $this->infoDictionaryBuilder->build($document, $serializedAt),
            );
        }

        if ($state->encryptObjectId !== null) {
            $objects[] = IndirectObject::plain(
                $state->encryptObjectId,
                $encryptObjectContents,
                false,
            );
        }

        return $objects;
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }
}
