<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;

use DateTimeImmutable;

use InvalidArgumentException;

use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Encryption\EncryptDictionaryBuilder;
use Kalle\Pdf\Encryption\EncryptionProfileResolver;
use Kalle\Pdf\Encryption\ObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileStructure;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Trailer;
use Random\RandomException;

use function sprintf;
use function str_replace;

/**
 * Builds a minimal serialization plan from a prepared document.
 */
final class DocumentSerializationPlanBuilder
{
    public function __construct(
        private readonly EncryptionProfileResolver $encryptionProfileResolver = new EncryptionProfileResolver(),
        private readonly StandardSecurityHandler $standardSecurityHandler = new StandardSecurityHandler(),
        private readonly EncryptDictionaryBuilder $encryptDictionaryBuilder = new EncryptDictionaryBuilder(),
        private readonly DocumentSerializationPlanValidator $validator = new DocumentSerializationPlanValidator(),
        private readonly DocumentSerializationPlanObjectIdAllocator $objectIdAllocator = new DocumentSerializationPlanObjectIdAllocator(),
        private readonly DocumentPageAndFormObjectBuilder $pageAndFormObjectBuilder = new DocumentPageAndFormObjectBuilder(),
        private readonly DocumentFontAndImageObjectBuilder $fontAndImageObjectBuilder = new DocumentFontAndImageObjectBuilder(),
        private readonly DocumentAttachmentObjectBuilder $attachmentObjectBuilder = new DocumentAttachmentObjectBuilder(),
        private readonly DocumentMetadataObjectBuilder $metadataObjectBuilder = new DocumentMetadataObjectBuilder(),
        private readonly DocumentTaggedPdfObjectBuilder $taggedPdfObjectBuilder = new DocumentTaggedPdfObjectBuilder(),
    ) {
    }

    public function build(Document $document): DocumentSerializationPlan
    {
        $debugger = $document->debugger;
        $this->validator->assertBuildable($document);
        $serializedAt = new DateTimeImmutable('now');
        $collectTaggedLinkStructure = fn (int $nextStructParentId): array => $this->taggedPdfObjectBuilder->collectTaggedLinkStructure($document, $nextStructParentId);
        $collectTaggedFormStructure = function (
            array $acroFormFieldObjectIds,
            array $acroFormFieldRelatedObjectIds,
            int $nextStructParentId,
        ) use ($document): array {
            /** @var list<int> $normalizedFieldObjectIds */
            $normalizedFieldObjectIds = array_values($acroFormFieldObjectIds);
            /** @var array<int, list<int>> $normalizedFieldRelatedObjectIds */
            $normalizedFieldRelatedObjectIds = $acroFormFieldRelatedObjectIds;

            return $this->taggedPdfObjectBuilder->collectTaggedFormStructure(
                $document,
                $normalizedFieldObjectIds,
                $normalizedFieldRelatedObjectIds,
                $nextStructParentId,
            );
        };
        $collectNamedDestinations = fn (): array => $this->collectNamedDestinations($document);

        $state = $this->objectIdAllocator->allocate(
            $document,
            $collectTaggedLinkStructure,
            $collectTaggedFormStructure,
            $collectNamedDestinations,
        );
        $objectEncryptor = null;
        $encryptObjectContents = '';
        $documentId = null;

        if ($document->encryption !== null) {
            $documentId = $this->generateDocumentId();
            $encryptionProfile = $this->encryptionProfileResolver->resolve($document->profile, $document->encryption);
            $securityHandlerData = $this->standardSecurityHandler->build(
                $document->encryption,
                $encryptionProfile,
                $documentId,
            );
            $objectEncryptor = new ObjectEncryptor($encryptionProfile, $securityHandlerData);
            $encryptObjectContents = $this->encryptDictionaryBuilder->build($encryptionProfile, $securityHandlerData);
        } elseif ($document->profile->isPdfA()) {
            $documentId = $this->generateDocumentId();
        }

        $objects = [
            IndirectObject::plain(
                1,
                $this->buildCatalogDictionary(
                    $document,
                    $state->metadataObjectId,
                    $state->iccProfileObjectId,
                    $state->structTreeRootObjectId,
                    $state->namedDestinations,
                    $state->attachmentObjectIds,
                    $state->acroFormObjectId,
                ),
            ),
            IndirectObject::plain(
                2,
                '<< /Type /Pages /Count ' . count($state->pageObjectIds) . ' /Kids [' . $this->buildKidsReferences($state->pageObjectIds) . '] >>',
            ),
        ];

        $pageObjects = $this->pageAndFormObjectBuilder->buildPageObjects($document, $state, $debugger);
        $objects = [...$objects, ...$pageObjects];

        $fontAndImageObjects = $this->fontAndImageObjectBuilder->buildObjects($document, $state);
        $objects = [...$objects, ...$fontAndImageObjects];

        $attachmentObjects = $this->attachmentObjectBuilder->buildObjects($document, $state);
        $objects = [...$objects, ...$attachmentObjects];

        $acroFormObjects = $this->pageAndFormObjectBuilder->buildAcroFormObjects($document, $state);
        $objects = [...$objects, ...$acroFormObjects];

        $taggedPdfObjects = $this->taggedPdfObjectBuilder->buildObjects($document, $state);
        $objects = [...$objects, ...$taggedPdfObjects];

        $metadataObjects = $this->metadataObjectBuilder->buildObjects($document, $state, $serializedAt, $encryptObjectContents);
        $objects = [...$objects, ...$metadataObjects];
        $this->logCreatedObjects($debugger, $objects);

        return new DocumentSerializationPlan(
            objects: $objects,
            fileStructure: new FileStructure(
                version: $document->version(),
                trailer: new Trailer(
                    size: count($objects) + 1,
                    rootObjectId: 1,
                    infoObjectId: $state->infoObjectId,
                    encryptObjectId: $state->encryptObjectId,
                    documentId: $documentId,
                ),
            ),
            objectEncryptor: $objectEncryptor,
        );
    }

    /**
     * @param array<string, string> $namedDestinations
     * @param list<int> $attachmentObjectIds
     */
    private function buildCatalogDictionary(
        Document $document,
        ?int $metadataObjectId,
        ?int $iccProfileObjectId,
        ?int $structTreeRootObjectId,
        array $namedDestinations,
        array $attachmentObjectIds,
        ?int $acroFormObjectId,
    ): string {
        $entries = [
            '/Type /Catalog',
            '/Pages 2 0 R',
        ];

        $entries = [
            ...$entries,
            ...$this->metadataObjectBuilder->buildCatalogEntries($document, $metadataObjectId, $iccProfileObjectId),
        ];

        if ($document->language !== null) {
            $entries[] = '/Lang ' . $this->pdfString($document->language);
        }

        if ($document->profile->requiresTaggedPdf()) {
            $entries[] = '/MarkInfo << /Marked true >>';
        }

        if ($structTreeRootObjectId !== null) {
            $entries[] = '/StructTreeRoot ' . $structTreeRootObjectId . ' 0 R';
        }

        if ($namedDestinations !== []) {
            $destEntries = [];

            foreach ($namedDestinations as $name => $destination) {
                $destEntries[] = '/' . $name . ' ' . $destination;
            }

            $entries[] = '/Dests << ' . implode(' ', $destEntries) . ' >>';
        }

        $entries = [
            ...$entries,
            ...$this->attachmentObjectBuilder->buildCatalogEntries($document, $attachmentObjectIds),
        ];

        if ($acroFormObjectId !== null) {
            $entries[] = '/AcroForm ' . $acroFormObjectId . ' 0 R';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @return array<string, string>
     */
    private function collectNamedDestinations(Document $document): array
    {
        $destinations = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->namedDestinations as $destination) {
                $pageObjectId = 3 + ($pageIndex * 2);

                $destinations[$this->pdfName($destination->name)] = $destination->isFit()
                    ? '[' . $pageObjectId . ' 0 R /Fit]'
                    : '[' . $pageObjectId . ' 0 R /XYZ '
                        . $this->formatNumber($destination->x ?? 0.0)
                        . ' '
                        . $this->formatNumber($destination->y ?? 0.0)
                        . ' null]';
            }
        }

        return $destinations;
    }

    private function generateDocumentId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (RandomException) {
            return md5(uniqid((string) mt_rand(), true));
        }
    }

    /**
     * @param list<int> $pageObjectIds
     */
    private function buildKidsReferences(array $pageObjectIds): string
    {
        if ($pageObjectIds === []) {
            return '';
        }

        return implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            $pageObjectIds,
        ));
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    private function pdfName(string $value): string
    {
        $encoded = '';

        foreach (str_split($value) as $character) {
            $ord = ord($character);

            if (
                ($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || $character === '-'
                || $character === '_'
                || $character === '.'
            ) {
                $encoded .= $character;

                continue;
            }

            $encoded .= '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function logCreatedObjects(Debugger $debugger, array $objects): void
    {
        $objectCount = count($objects);

        foreach ($objects as $object) {
            $debugger->pdf('object.created', [
                'object_id' => $object->objectId,
                'type' => $this->inferObjectType($object->contents),
                'length' => strlen($object->contents),
                'contents_id' => $this->extractReferenceObjectId($object->contents, '/Contents'),
                'parent_id' => $this->extractReferenceObjectId($object->contents, '/Parent'),
                'object_count' => $objectCount,
            ]);
        }
    }

    private function inferObjectType(string $contents): ?string
    {
        if (!preg_match('/\/Type\s*\/([A-Za-z0-9]+)/', $contents, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function extractReferenceObjectId(string $contents, string $entry): ?int
    {
        if (!preg_match('/' . preg_quote($entry, '/') . '\s+(\d+)\s+0\s+R/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

}
