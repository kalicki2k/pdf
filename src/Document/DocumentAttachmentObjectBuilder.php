<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_map;
use function implode;

use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Writer\IndirectObject;

use function str_replace;

final readonly class DocumentAttachmentObjectBuilder
{
    public function __construct(
        private DocumentAttachmentRelationshipResolver $attachmentRelationshipResolver = new DocumentAttachmentRelationshipResolver(),
    ) {
    }

    /**
     * @param list<int> $attachmentObjectIds
     * @return list<string>
     */
    public function buildCatalogEntries(Document $document, array $attachmentObjectIds): array
    {
        if ($attachmentObjectIds === []) {
            return [];
        }

        $entries = [
            '/Names ' . $this->buildEmbeddedFilesNameDictionary($document, $attachmentObjectIds),
        ];
        $associatedFileObjectIds = $this->associatedFileObjectIds($document, $attachmentObjectIds);

        if ($associatedFileObjectIds !== []) {
            $entries[] = '/AF [' . implode(' ', array_map(
                static fn (int $objectId): string => $objectId . ' 0 R',
                $associatedFileObjectIds,
            )) . ']';
        }

        return $entries;
    }

    /**
     * @return list<IndirectObject>
     */
    public function buildObjects(Document $document, DocumentSerializationPlanBuildState $state): array
    {
        $objects = [];

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            $embeddedFileObjectId = $state->embeddedFileObjectIds[$attachmentIndex];
            $attachmentObjectId = $state->attachmentObjectIds[$attachmentIndex];

            $objects[] = IndirectObject::stream(
                $embeddedFileObjectId,
                $this->buildEmbeddedFileStreamDictionary($attachment),
                $attachment->embeddedFile->contents,
            );
            $objects[] = IndirectObject::plain(
                $attachmentObjectId,
                $this->buildFileSpecificationDictionary($document, $attachment, $embeddedFileObjectId),
            );
        }

        return $objects;
    }

    /**
     * @param list<int> $attachmentObjectIds
     */
    private function buildEmbeddedFilesNameDictionary(Document $document, array $attachmentObjectIds): string
    {
        $entries = [];

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            $entries[] = $this->pdfString($attachment->filename);
            $entries[] = $attachmentObjectIds[$attachmentIndex] . ' 0 R';
        }

        return '<< /EmbeddedFiles << /Names [' . implode(' ', $entries) . '] >> >>';
    }

    private function buildEmbeddedFileStreamDictionary(FileAttachment $attachment): string
    {
        $size = $attachment->embeddedFile->size();
        $entries = [
            '/Type /EmbeddedFile',
            '/Length ' . $size,
            '/Params << /Size ' . $size . ' >>',
        ];

        if ($attachment->embeddedFile->mimeType !== null) {
            $entries[] = '/Subtype /' . $this->pdfName($attachment->embeddedFile->mimeType);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function buildFileSpecificationDictionary(
        Document $document,
        FileAttachment $attachment,
        int $embeddedFileObjectId,
    ): string {
        $entries = [
            '/Type /Filespec',
            '/F ' . $this->pdfString($attachment->filename),
            '/UF ' . $this->pdfString($attachment->filename),
            '/EF << /F ' . $embeddedFileObjectId . ' 0 R /UF ' . $embeddedFileObjectId . ' 0 R >>',
        ];

        if ($attachment->description !== null && $attachment->description !== '') {
            $entries[] = '/Desc ' . $this->pdfString($attachment->description);
        }

        $relationship = $this->attachmentRelationshipResolver->resolve($document, $attachment);

        if ($relationship !== null) {
            $entries[] = '/AFRelationship /' . $relationship->value;
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @param list<int> $attachmentObjectIds
     * @return list<int>
     */
    private function associatedFileObjectIds(Document $document, array $attachmentObjectIds): array
    {
        $associatedFileObjectIds = [];

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            if ($this->attachmentRelationshipResolver->resolve($document, $attachment) === null) {
                continue;
            }

            $associatedFileObjectIds[] = $attachmentObjectIds[$attachmentIndex];
        }

        return $associatedFileObjectIds;
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
}
