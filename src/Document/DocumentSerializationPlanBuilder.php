<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function str_replace;

use DateTimeImmutable;
use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Encryption\EncryptDictionaryBuilder;
use Kalle\Pdf\Encryption\EncryptionProfileResolver;
use Kalle\Pdf\Encryption\ObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileStructure;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Trailer;
use Random\RandomException;

/**
 * Builds a minimal serialization plan from a prepared document.
 */
final readonly class DocumentSerializationPlanBuilder
{
    public function __construct(
        private EncryptionProfileResolver $encryptionProfileResolver = new EncryptionProfileResolver(),
        private StandardSecurityHandler $standardSecurityHandler = new StandardSecurityHandler(),
        private EncryptDictionaryBuilder $encryptDictionaryBuilder = new EncryptDictionaryBuilder(),
        private DocumentSerializationPlanValidator $validator = new DocumentSerializationPlanValidator(),
        private PdfA1aTaggedStructureValidator $pdfA1aTaggedStructureValidator = new PdfA1aTaggedStructureValidator(),
        private DocumentSerializationPlanObjectIdAllocator $objectIdAllocator = new DocumentSerializationPlanObjectIdAllocator(),
        private DocumentPageAndFormObjectBuilder $pageAndFormObjectBuilder = new DocumentPageAndFormObjectBuilder(),
        private DocumentFontAndImageObjectBuilder $fontAndImageObjectBuilder = new DocumentFontAndImageObjectBuilder(),
        private DocumentAttachmentObjectBuilder $attachmentObjectBuilder = new DocumentAttachmentObjectBuilder(),
        private DocumentMetadataObjectBuilder $metadataObjectBuilder = new DocumentMetadataObjectBuilder(),
        private DocumentOutlineObjectBuilder $outlineObjectBuilder = new DocumentOutlineObjectBuilder(),
        private DocumentTaggedPdfObjectBuilder $taggedPdfObjectBuilder = new DocumentTaggedPdfObjectBuilder(),
        private PdfAObjectGraphValidator $pdfAObjectGraphValidator = new PdfAObjectGraphValidator(),
        private PdfA1ObjectGraphValidator $pdfA1ObjectGraphValidator = new PdfA1ObjectGraphValidator(),
    ) {
    }

    public function build(Document $document): DocumentSerializationPlan
    {
        $debugger = $document->debugger;
        $serializedAt = new DateTimeImmutable('now');
        $this->validator->assertBuildable($document, $serializedAt);
        $collectTaggedLinkStructure = fn (int $nextStructParentId): array => $this->taggedPdfObjectBuilder->collectTaggedLinkStructure($document, $nextStructParentId);
        $collectTaggedPageAnnotationStructure = fn (int $nextStructParentId): array => $this->taggedPdfObjectBuilder->collectTaggedPageAnnotationStructure($document, $nextStructParentId);
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
            $collectTaggedPageAnnotationStructure,
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
                    $state->outlineRootObjectId,
                    $state->attachmentObjectIds,
                    $state->acroFormObjectId,
                    $state,
                ),
            ),
            IndirectObject::plain(
                2,
                '<< /Type /Pages /Count ' . count($state->pageObjectIds) . ' /Kids [' . $this->buildKidsReferences($state->pageObjectIds) . '] >>',
            ),
        ];

        $pageObjects = $this->pageAndFormObjectBuilder->buildPageObjects($document, $state, $debugger);
        $this->appendObjects($objects, $pageObjects);

        $optionalContentObjects = $this->buildOptionalContentObjects($document, $state);
        $this->appendObjects($objects, $optionalContentObjects);

        $fontAndImageObjects = $this->fontAndImageObjectBuilder->buildObjects($document, $state);
        $this->appendObjects($objects, $fontAndImageObjects);

        $attachmentObjects = $this->attachmentObjectBuilder->buildObjects($document, $state);
        $this->appendObjects($objects, $attachmentObjects);

        $outlineObjects = $this->outlineObjectBuilder->buildObjects($document, $state);
        $this->appendObjects($objects, $outlineObjects);

        $acroFormObjects = $this->pageAndFormObjectBuilder->buildAcroFormObjects($document, $state);
        $this->appendObjects($objects, $acroFormObjects);

        $taggedPdfObjects = $this->taggedPdfObjectBuilder->buildObjects($document, $state);
        $this->appendObjects($objects, $taggedPdfObjects);
        $this->pdfA1aTaggedStructureValidator->assertValid($document, $state, $objects);

        $metadataObjects = $this->metadataObjectBuilder->buildObjects($document, $state, $serializedAt, $encryptObjectContents);
        $this->appendObjects($objects, $metadataObjects);
        $this->pdfAObjectGraphValidator->assertValid($document, $state, $objects);
        $this->pdfA1ObjectGraphValidator->assertValid($document, $state, $objects);
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
        ?int $outlineRootObjectId,
        array $attachmentObjectIds,
        ?int $acroFormObjectId,
        DocumentSerializationPlanBuildState $state,
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
            ...$this->outlineObjectBuilder->buildCatalogEntries($outlineRootObjectId),
        ];

        $entries = [
            ...$entries,
            ...$this->attachmentObjectBuilder->buildCatalogEntries($document, $attachmentObjectIds),
        ];

        if ($acroFormObjectId !== null) {
            $entries[] = '/AcroForm ' . $acroFormObjectId . ' 0 R';
        }

        $entries = [
            ...$entries,
            ...$this->buildOptionalContentCatalogEntries($document, $state),
        ];

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @return list<IndirectObject>
     */
    private function buildOptionalContentObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        $groupsByKey = $this->optionalContentGroupsByKey($document);
        $objects = [];

        foreach ($state->optionalContentGroupObjectIds as $key => $objectId) {
            $group = $groupsByKey[$key] ?? null;

            if ($group === null) {
                continue;
            }

            $objects[] = IndirectObject::plain(
                $objectId,
                '<< /Type /OCG /Name ' . $this->pdfString($group->name) . ' >>',
            );
        }

        return $objects;
    }

    /**
     * @return list<string>
     */
    private function buildOptionalContentCatalogEntries(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        if ($state->optionalContentGroupObjectIds === [] || !$document->profile->supportsOptionalContentGroups()) {
            return [];
        }

        $references = implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            array_values($state->optionalContentGroupObjectIds),
        ));
        $groupsByKey = $this->optionalContentGroupsByKey($document);
        $onReferences = [];
        $offReferences = [];

        foreach ($state->optionalContentGroupObjectIds as $key => $objectId) {
            $group = $groupsByKey[$key] ?? null;

            if ($group === null) {
                continue;
            }

            if ($group->visible) {
                $onReferences[] = $objectId . ' 0 R';
            } else {
                $offReferences[] = $objectId . ' 0 R';
            }
        }

        $defaultConfigEntries = [
            '/Name (Layers)',
            '/Order [' . $references . ']',
        ];

        if ($onReferences !== []) {
            $defaultConfigEntries[] = '/ON [' . implode(' ', $onReferences) . ']';
        }

        if ($offReferences !== []) {
            $defaultConfigEntries[] = '/OFF [' . implode(' ', $offReferences) . ']';
        }

        return [
            '/OCProperties << /OCGs [' . $references . '] /D << ' . implode(' ', $defaultConfigEntries) . ' >> >>',
        ];
    }

    /**
     * @return array<string, OptionalContentGroup>
     */
    private function optionalContentGroupsByKey(Document $document): array
    {
        $groups = [];

        foreach ($document->pages as $page) {
            foreach ($page->optionalContentGroups as $optionalContentGroup) {
                $groups[$optionalContentGroup->key()] = $optionalContentGroup;
            }
        }

        return $groups;
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

    /**
     * @param list<IndirectObject> $objects
     * @param list<IndirectObject> $additionalObjects
     */
    private function appendObjects(array &$objects, array $additionalObjects): void
    {
        foreach ($additionalObjects as $object) {
            $objects[] = $object;
        }
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
